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

        // Statistiques d'onboarding stateless
        $onboardingStats = $this->getOnboardingStats();

        return [
            'total_tenants' => $totalTenants,
            'active_tenants' => $activeTenants,
            'inactive_tenants' => $totalTenants - $activeTenants,
            'total_users' => $totalUsers,
            'total_activities' => $totalActivities,
            'onboarding_stats' => $onboardingStats,
        ];
    }

    /**
     * Obtenir les statistiques d'onboarding stateless
     */
    public function getOnboardingStats(): array
    {
        $total = \App\Models\OnboardingRegistration::count();
        $pending = \App\Models\OnboardingRegistration::where('status', 'pending')->count();
        $activated = \App\Models\OnboardingRegistration::where('status', 'activated')->count();
        $cancelled = \App\Models\OnboardingRegistration::where('status', 'cancelled')->count();

        // Onboardings bloqués (pending > 24h)
        $stuck = \App\Models\OnboardingRegistration::where('status', 'pending')
            ->where('created_at', '<', now()->subHours(24))
            ->count();

        // Taux de succès
        $successRate = $total > 0 ? round(($activated / $total) * 100, 2) : 0;

        // Temps moyen de provisioning (pour les activés)
        $avgProvisioningTime = \App\Models\OnboardingRegistration::where('status', 'activated')
            ->whereNotNull('updated_at')
            ->get()
            ->map(function($onboarding) {
                return $onboarding->created_at->diffInMinutes($onboarding->updated_at);
            })
            ->avg();

        // Onboardings par application
        $byApplication = \App\Models\OnboardingRegistration::select('application_id', DB::raw('count(*) as count'))
            ->with('application')
            ->groupBy('application_id')
            ->get()
            ->mapWithKeys(function($item) {
                return [$item->application->app_name ?? 'Unknown' => $item->count];
            })
            ->toArray();

        // Onboardings des 7 derniers jours
        $last7Days = \App\Models\OnboardingRegistration::where('created_at', '>=', now()->subDays(7))
            ->count();

        // Onboardings des 30 derniers jours
        $last30Days = \App\Models\OnboardingRegistration::where('created_at', '>=', now()->subDays(30))
            ->count();

        return [
            'total' => $total,
            'pending' => $pending,
            'activated' => $activated,
            'cancelled' => $cancelled,
            'stuck' => $stuck,
            'success_rate' => $successRate,
            'avg_provisioning_time_minutes' => round($avgProvisioningTime ?? 0, 2),
            'by_application' => $byApplication,
            'last_7_days' => $last7Days,
            'last_30_days' => $last30Days,
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
