<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\OnboardingSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;

class TenantService
{
    /**
     * Récupère le nom de la base de données pour un sous-domaine donné
     * IMPORTANT: Les modèles Tenant et OnboardingSession utilisent toujours la connexion 'mysql' (base principale)
     */
    public function getTenantDatabase(string $subdomain): ?string
    {
        $cacheKey = "tenant_db_{$subdomain}";
        
        return Cache::remember($cacheKey, 3600, function () use ($subdomain) {
            try {
                // D'abord chercher dans la table tenants (base principale)
                // Le modèle Tenant a $connection = 'mysql', donc il utilisera toujours la base principale
                $tenant = Tenant::where('subdomain', $subdomain)
                    ->where('status', 'active')
                    ->first();
                
                if ($tenant) {
                    return $tenant->database_name;
                }
                
                // Fallback vers OnboardingSession pour compatibilité (base principale aussi)
                // Le modèle OnboardingSession a $connection = 'mysql'
                $onboarding = OnboardingSession::where('subdomain', $subdomain)
                    ->where('status', 'completed')
                    ->first();
                
                return $onboarding ? $onboarding->database_name : null;
            } catch (\Exception $e) {
                Log::error("Erreur lors de la récupération de la base du tenant: " . $e->getMessage());
                throw $e;
            }
        });
    }

    /**
     * Trouve tous les tenants où un email existe comme utilisateur
     *
     * @param string $email
     * @return array Array of ['tenant' => Tenant, 'user' => User]
     */
    public function findTenantsByUserEmail(string $email): array
    {
        $results = [];
        
        try {
            // Récupérer tous les tenants actifs depuis la base principale
            $tenants = Tenant::where('status', 'active')->get();
            
            foreach ($tenants as $tenant) {
                try {
                    // Basculer vers la base du tenant
                    $this->switchToTenantDatabase($tenant->database_name);
                    
                    // Chercher l'utilisateur dans cette base
                    $user = \App\Models\Tenant\User::where('email', $email)->first();
                    
                    if ($user) {
                        $results[] = [
                            'tenant' => $tenant,
                            'user' => [
                                'id' => $user->id,
                                'name' => $user->name,
                                'email' => $user->email,
                                'role' => $user->role,
                            ],
                        ];
                    }
                } catch (\Exception $e) {
                    // Si erreur sur un tenant, continuer avec les autres
                    Log::warning("Erreur lors de la recherche dans le tenant {$tenant->subdomain}: " . $e->getMessage());
                    continue;
                } finally {
                    // Toujours revenir à la base principale
                    Config::set('database.default', 'mysql');
                    DB::purge('tenant');
                }
            }
        } catch (\Exception $e) {
            Log::error("Erreur lors de la recherche des tenants par email: " . $e->getMessage());
        }
        
        return $results;
    }

    /**
     * Configure la connexion de base de données pour un tenant spécifique
     */
    public function switchToTenantDatabase(string $databaseName): void
    {
        if (empty($databaseName)) {
            throw new \Exception('Nom de base de données invalide');
        }

        // Récupérer la configuration de base
        $defaultConfig = config('database.connections.mysql');
        
        // Créer une nouvelle connexion dynamique pour ce tenant
        Config::set("database.connections.tenant", [
            'driver' => 'mysql',
            'host' => $defaultConfig['host'],
            'port' => $defaultConfig['port'],
            'database' => $databaseName,
            'username' => $defaultConfig['username'],
            'password' => $defaultConfig['password'],
            'charset' => $defaultConfig['charset'],
            'collation' => $defaultConfig['collation'],
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => $defaultConfig['strict'],
            'engine' => null,
        ]);

        // Basculer la connexion par défaut vers le tenant
        DB::purge('tenant');
        Config::set('database.default', 'tenant');
    }

    /**
     * Récupère les informations complètes d'un tenant
     * IMPORTANT: Le modèle Tenant utilise toujours la connexion 'mysql' (base principale)
     */
    public function getTenantInfo(string $subdomain): ?Tenant
    {
        $cacheKey = "tenant_info_{$subdomain}";
        
        return Cache::remember($cacheKey, 3600, function () use ($subdomain) {
            try {
                // Le modèle Tenant a $connection = 'mysql', donc il utilisera toujours la base principale
                return Tenant::where('subdomain', $subdomain)->first();
            } catch (\Exception $e) {
                Log::error("Erreur lors de la récupération des infos du tenant: " . $e->getMessage());
                throw $e;
            }
        });
    }

    /**
     * Récupère un tenant par sous-domaine
     */
    public function getTenantBySubdomain(string $subdomain): ?Tenant
    {
        return $this->getTenantInfo($subdomain);
    }

    /**
     * Vérifie si un tenant existe et est actif
     * IMPORTANT: Le modèle Tenant utilise toujours la connexion 'mysql' (base principale)
     */
    public function tenantExists(string $subdomain): bool
    {
        try {
            // Le modèle Tenant a $connection = 'mysql', donc il utilisera toujours la base principale
            return Tenant::where('subdomain', $subdomain)
                ->where('status', 'active')
                ->exists();
        } catch (\Exception $e) {
            Log::error("Erreur lors de la vérification du tenant: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Crée un nouveau tenant
     */
    public function createTenant(array $data): Tenant
    {
        $tenant = Tenant::create([
            'subdomain' => $data['subdomain'],
            'database_name' => $data['database_name'],
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'status' => $data['status'] ?? 'active',
            'plan' => $data['plan'] ?? null,
        ]);

        // Nettoyer le cache
        $this->clearTenantCache($tenant->subdomain);

        Log::info("Tenant créé : {$tenant->subdomain}");

        return $tenant;
    }

    /**
     * Récupère tous les tenants
     */
    public function getAllTenants(array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = Tenant::query();

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['plan'])) {
            $query->where('plan', $filters['plan']);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Met à jour le statut d'un tenant
     */
    public function updateTenantStatus(string $subdomain, string $status): bool
    {
        $tenant = Tenant::where('subdomain', $subdomain)->first();

        if (!$tenant) {
            return false;
        }

        $tenant->update(['status' => $status]);
        $this->clearTenantCache($subdomain);

        Log::info("Statut du tenant {$subdomain} mis à jour : {$status}");

        return true;
    }

    /**
     * Supprime un tenant (soft delete)
     */
    public function deleteTenant(string $subdomain): bool
    {
        $tenant = Tenant::where('subdomain', $subdomain)->first();

        if (!$tenant) {
            return false;
        }

        $tenant->delete();
        $this->clearTenantCache($subdomain);

        Log::info("Tenant supprimé : {$subdomain}");

        return true;
    }

    /**
     * Exécute les migrations dans la base de données du tenant
     * Note: Cette méthode suppose que la connexion est déjà basculée vers le tenant
     */
    public function runTenantMigrations(string $databaseName): void
    {
        // S'assurer qu'on est bien sur la base du tenant
        $currentDatabase = DB::connection()->getDatabaseName();
        if ($currentDatabase !== $databaseName) {
            $this->switchToTenantDatabase($databaseName);
        }

        try {
            // Exécuter les migrations depuis le dossier tenant
            $migrationPath = database_path('migrations/tenant');
            
            // Vérifier que le dossier existe
            if (!is_dir($migrationPath)) {
                throw new \Exception("Le dossier de migrations tenant n'existe pas : {$migrationPath}");
            }
            
            // Exécuter les migrations via Artisan sur la connexion actuelle (tenant)
            Artisan::call('migrate', [
                '--path' => 'database/migrations/tenant',
                '--database' => Config::get('database.default'),
                '--force' => true,
            ]);

            Log::info("Migrations exécutées pour la base : {$databaseName}");
        } catch (\Exception $e) {
            Log::error("Erreur lors de l'exécution des migrations tenant : " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Nettoie le cache d'un tenant
     */
    public function clearTenantCache(string $subdomain): void
    {
        Cache::forget("tenant_db_{$subdomain}");
        Cache::forget("tenant_info_{$subdomain}");
    }
}
