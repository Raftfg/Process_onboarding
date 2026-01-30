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
            $onboarding = OnboardingSession::where('subdomain', $subdomain)
                ->where('status', 'completed')
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
        return OnboardingSession::where('subdomain', $subdomain)
            ->where('status', 'completed')
            ->first();
    }

    /**
     * Vérifie si un tenant existe et est actif
     */
    public function tenantExists(string $subdomain): bool
    {
        return OnboardingSession::where('subdomain', $subdomain)
            ->where('status', 'completed')
            ->exists();
    }

    /**
     * Nettoie le cache d'un tenant
     */
    public function clearTenantCache(string $subdomain): void
    {
        Cache::forget("tenant_db_{$subdomain}");
    }
}
