<?php

namespace App\Services;

use App\Models\OnboardingSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TenantService
{
    /**
     * Récupère le nom de la base de données pour un sous-domaine donné
     */
    public function getTenantDatabase(string $subdomain): ?string
    {
        $cacheKey = "tenant_db_{$subdomain}";
        
        return Cache::remember($cacheKey, 3600, function () use ($subdomain) {
            // Use Query Builder instead of Eloquent to avoid Model overhead/issues early in boot
            $onboarding = DB::connection('mysql')
                ->table('onboarding_sessions')
                ->where('subdomain', $subdomain)
                ->whereIn('status', ['completed', 'pending_activation'])
                ->first();
            
            return $onboarding ? $onboarding->database_name : null;
        });
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
     */
    public function getTenantInfo(string $subdomain): ?OnboardingSession
    {
        // FORCER l'utilisation de la connexion principale
        // Accepter les statuts 'completed' et 'pending_activation'
        return OnboardingSession::on('mysql')
            ->where('subdomain', $subdomain)
            ->whereIn('status', ['completed', 'pending_activation'])
            ->first();
    }

    /**
     * Vérifie si un tenant existe et est actif
     */
    public function tenantExists(string $subdomain): bool
    {
        return DB::connection('mysql')
            ->table('onboarding_sessions')
            ->where('subdomain', $subdomain)
            ->whereIn('status', ['completed', 'pending_activation'])
            ->exists();
    }

    /**
     * Nettoie le cache d'un tenant
     */
    public function clearTenantCache(string $subdomain): void
    {
        Cache::forget("tenant_db_{$subdomain}");
    }

    /**
     * Trouve tous les sous-domaines associés à un email
     * 
     * @param string $email
     * @return array Array of ['subdomain' => string, 'database_name' => string, 'organization_name' => string, 'user_role' => string|null]
     */
    public function findSubdomainsByEmail(string $email): array
    {
        $results = [];
        
        // Sauvegarder la connexion actuelle
        $defaultConnection = config('database.default');
        $wasUsingTenant = ($defaultConnection !== 'mysql');
        
        try {
            // S'assurer qu'on utilise la connexion principale pour les requêtes OnboardingSession
            // Ne pas purger mysql si c'est déjà la connexion par défaut
            if ($wasUsingTenant) {
                Config::set('database.default', 'mysql');
                DB::purge('tenant');
            }
            
            // Rechercher dans onboarding_sessions où admin_email = email
            $onboardingSessions = OnboardingSession::on('mysql')
                ->where('admin_email', $email)
                ->where('status', 'completed')
                ->whereNotNull('database_name')
                ->get();
            
            // Pour chaque tenant trouvé, vérifier si l'email existe dans la base du tenant
            foreach ($onboardingSessions as $session) {
                try {
                    // Créer une connexion temporaire à la base du tenant
                    $defaultConfig = config('database.connections.mysql');
                    $tempConnectionName = 'temp_tenant_' . md5($session->database_name);
                    
                    Config::set("database.connections.{$tempConnectionName}", [
                        'driver' => 'mysql',
                        'host' => $defaultConfig['host'],
                        'port' => $defaultConfig['port'],
                        'database' => $session->database_name,
                        'username' => $defaultConfig['username'],
                        'password' => $defaultConfig['password'],
                        'charset' => $defaultConfig['charset'],
                        'collation' => $defaultConfig['collation'],
                        'prefix' => '',
                        'prefix_indexes' => true,
                        'strict' => $defaultConfig['strict'] ?? true,
                        'engine' => null,
                    ]);
                    
                    // Purger la connexion temporaire pour forcer la recréation
                    DB::purge($tempConnectionName);
                    
                    // Vérifier si l'email existe dans la table users de cette base
                    $user = DB::connection($tempConnectionName)
                        ->table('users')
                        ->where('email', $email)
                        ->first();
                    
                    if ($user) {
                        $results[] = [
                            'subdomain' => $session->subdomain,
                            'database_name' => $session->database_name,
                            'organization_name' => $session->organization_name,
                            'user_role' => $user->role ?? null,
                        ];
                    }
                    
                    // Nettoyer la connexion temporaire
                    DB::purge($tempConnectionName);
                } catch (\Exception $e) {
                    Log::warning("Erreur lors de la vérification du tenant {$session->subdomain}: " . $e->getMessage());
                    // Si l'email est admin_email, l'ajouter quand même (il sera créé lors de l'onboarding)
                    if ($session->admin_email === $email) {
                        $results[] = [
                            'subdomain' => $session->subdomain,
                            'database_name' => $session->database_name,
                            'organization_name' => $session->organization_name,
                            'user_role' => 'admin', // Probablement admin si c'est l'admin_email
                        ];
                    }
                }
            }
            
            // Rechercher aussi dans toutes les bases de données tenant pour trouver d'autres utilisateurs
            // (utilisateurs créés après l'onboarding)
            $allSessions = OnboardingSession::on('mysql')
                ->where('status', 'completed')
                ->whereNotNull('database_name')
                ->get();
            
            foreach ($allSessions as $session) {
                // Ignorer ceux déjà trouvés
                $alreadyFound = collect($results)->contains(function ($result) use ($session) {
                    return $result['subdomain'] === $session->subdomain;
                });
                
                if ($alreadyFound) {
                    continue;
                }
                
                try {
                    $defaultConfig = config('database.connections.mysql');
                    $tempConnectionName = 'temp_tenant_' . md5($session->database_name);
                    
                    Config::set("database.connections.{$tempConnectionName}", [
                        'driver' => 'mysql',
                        'host' => $defaultConfig['host'],
                        'port' => $defaultConfig['port'],
                        'database' => $session->database_name,
                        'username' => $defaultConfig['username'],
                        'password' => $defaultConfig['password'],
                        'charset' => $defaultConfig['charset'],
                        'collation' => $defaultConfig['collation'],
                        'prefix' => '',
                        'prefix_indexes' => true,
                        'strict' => $defaultConfig['strict'] ?? true,
                        'engine' => null,
                    ]);
                    
                    DB::purge($tempConnectionName);
                    
                    $user = DB::connection($tempConnectionName)
                        ->table('users')
                        ->where('email', $email)
                        ->first();
                    
                    if ($user) {
                        $results[] = [
                            'subdomain' => $session->subdomain,
                            'database_name' => $session->database_name,
                            'organization_name' => $session->organization_name,
                            'user_role' => $user->role ?? null,
                        ];
                    }
                    
                    // Nettoyer la connexion temporaire
                    DB::purge($tempConnectionName);
                } catch (\Exception $e) {
                    // Ignorer les erreurs silencieusement pour cette recherche secondaire
                    Log::debug("Erreur lors de la recherche dans {$session->subdomain}: " . $e->getMessage());
                }
            }
            
        } catch (\Exception $e) {
            Log::error("Erreur lors de la recherche des sous-domaines pour l'email {$email}: " . $e->getMessage());
        } finally {
            // Restaurer la connexion par défaut seulement si elle était différente
            if ($wasUsingTenant) {
                Config::set('database.default', $defaultConnection);
                // Ne pas purger mysql car on en a besoin
            }
        }
        
        return $results;
    }
}
