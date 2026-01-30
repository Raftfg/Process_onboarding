<?php

namespace App\Services;

use App\Models\OnboardingSession;
use App\Services\TenantService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class AdminService
{
    protected $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    /**
     * Obtenir les statistiques globales
     */
    public function getGlobalStats(): array
    {
        $totalTenants = OnboardingSession::on('mysql')
            ->where('status', 'completed')
            ->count();

        $activeTenants = OnboardingSession::on('mysql')
            ->where('status', 'completed')
            ->whereNotNull('database_name')
            ->count();

        $totalUsers = 0;
        $totalActivities = 0;

        // Compter les utilisateurs et activités dans toutes les bases tenant
        $tenants = OnboardingSession::on('mysql')
            ->where('status', 'completed')
            ->whereNotNull('database_name')
            ->get();

        foreach ($tenants as $tenant) {
            try {
                $stats = $this->getTenantStats($tenant->id);
                $totalUsers += $stats['total_users'] ?? 0;
                $totalActivities += $stats['total_activities'] ?? 0;
            } catch (\Exception $e) {
                Log::warning("Impossible de récupérer les stats pour le tenant {$tenant->id}: " . $e->getMessage());
            }
        }

        return [
            'total_tenants' => $totalTenants,
            'active_tenants' => $activeTenants,
            'inactive_tenants' => $totalTenants - $activeTenants,
            'total_users' => $totalUsers,
            'total_activities' => $totalActivities,
        ];
    }

    /**
     * Obtenir les statistiques d'un tenant spécifique
     */
    public function getTenantStats(int $tenantId): array
    {
        $tenant = OnboardingSession::on('mysql')->findOrFail($tenantId);

        if (!$tenant->database_name) {
            return [
                'total_users' => 0,
                'active_users' => 0,
                'total_activities' => 0,
                'total_notifications' => 0,
            ];
        }

        // Sauvegarder la connexion actuelle
        $originalConnection = Config::get('database.default');
        
        try {
            // Configurer la connexion tenant
            $defaultConfig = config('database.connections.mysql');
            Config::set('database.connections.tenant', [
                'driver' => 'mysql',
                'host' => $defaultConfig['host'],
                'port' => $defaultConfig['port'],
                'database' => $tenant->database_name,
                'username' => $defaultConfig['username'],
                'password' => $defaultConfig['password'],
                'charset' => $defaultConfig['charset'],
                'collation' => $defaultConfig['collation'],
            ]);

            DB::purge('tenant');
            $connection = DB::connection('tenant');

            $totalUsers = $connection->table('users')->count();
            $activeUsers = $connection->table('users')->where('status', 'active')->count();
            $totalActivities = $connection->table('activities')->count();
            $totalNotifications = $connection->table('notifications')->count();

            return [
                'total_users' => $totalUsers,
                'active_users' => $activeUsers,
                'inactive_users' => $totalUsers - $activeUsers,
                'total_activities' => $totalActivities,
                'total_notifications' => $totalNotifications,
            ];
        } catch (\Exception $e) {
            Log::error("Erreur lors de la récupération des stats du tenant {$tenantId}: " . $e->getMessage());
            return [
                'total_users' => 0,
                'active_users' => 0,
                'inactive_users' => 0,
                'total_activities' => 0,
                'total_notifications' => 0,
            ];
        } finally {
            // Restaurer la connexion par défaut
            Config::set('database.default', $originalConnection);
            DB::purge('tenant');
        }
    }

    /**
     * Obtenir la liste des utilisateurs d'un tenant
     */
    public function getTenantUsers(int $tenantId, int $limit = 50): array
    {
        $tenant = OnboardingSession::on('mysql')->findOrFail($tenantId);

        if (!$tenant->database_name) {
            return [];
        }

        // Sauvegarder la connexion actuelle
        $originalConnection = Config::get('database.default');
        
        try {
            // Configurer la connexion tenant
            $defaultConfig = config('database.connections.mysql');
            Config::set('database.connections.tenant', [
                'driver' => 'mysql',
                'host' => $defaultConfig['host'],
                'port' => $defaultConfig['port'],
                'database' => $tenant->database_name,
                'username' => $defaultConfig['username'],
                'password' => $defaultConfig['password'],
                'charset' => $defaultConfig['charset'],
                'collation' => $defaultConfig['collation'],
            ]);

            DB::purge('tenant');
            $connection = DB::connection('tenant');

            $users = $connection->table('users')
                ->select('id', 'name', 'email', 'role', 'status', 'created_at')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->toArray();

            return $users;
        } catch (\Exception $e) {
            Log::error("Erreur lors de la récupération des utilisateurs du tenant {$tenantId}: " . $e->getMessage());
            return [];
        } finally {
            // Restaurer la connexion par défaut
            Config::set('database.default', $originalConnection);
            DB::purge('tenant');
        }
    }

    /**
     * Activer/désactiver un tenant
     */
    public function toggleTenantStatus(int $tenantId): bool
    {
        $tenant = OnboardingSession::on('mysql')->findOrFail($tenantId);
        
        // Pour l'instant, on peut juste marquer le tenant comme actif/inactif
        // Dans une implémentation complète, on pourrait ajouter un champ 'is_active' à onboarding_sessions
        // Pour l'instant, on considère qu'un tenant avec database_name est actif
        
        return true;
    }

    /**
     * Supprimer un tenant et sa base de données
     */
    public function deleteTenant(int $tenantId): bool
    {
        $tenant = OnboardingSession::on('mysql')->findOrFail($tenantId);

        if (!$tenant->database_name) {
            $tenant->delete();
            return true;
        }

        try {
            // Supprimer la base de données
            DB::statement("DROP DATABASE IF EXISTS `{$tenant->database_name}`");
            
            // Supprimer l'enregistrement
            $tenant->delete();
            
            Log::info("Tenant supprimé: {$tenant->subdomain} (Base: {$tenant->database_name})");
            
            return true;
        } catch (\Exception $e) {
            Log::error("Erreur lors de la suppression du tenant {$tenantId}: " . $e->getMessage());
            throw $e;
        }
    }
}
