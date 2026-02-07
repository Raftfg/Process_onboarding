<?php

namespace App\Services;

use App\Models\OnboardingRegistration;
use App\Models\Application;
use App\Models\AppDatabase;
use App\Models\ApiKey;
use App\Services\SubdomainService;
use App\Services\OrganizationNameGenerator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class OnboardingRegistrationService
{
    protected $subdomainService;
    protected $organizationNameGenerator;

    public function __construct(SubdomainService $subdomainService, OrganizationNameGenerator $organizationNameGenerator)
    {
        $this->subdomainService = $subdomainService;
        $this->organizationNameGenerator = $organizationNameGenerator;
    }

    /**
     * Enregistre un nouvel onboarding (sans créer de tenant)
     * 
     * @param Application $application Application appelante
     * @param string $email Email de l'administrateur
     * @param string|null $organizationName Nom de l'organisation (optionnel)
     * @param array $metadata Métadonnées flexibles
     * @param bool $generateApiKey Générer une clé API si nécessaire
     * @return array
     */
    public function registerOnboarding(
        Application $application,
        string $email,
        ?string $organizationName = null,
        array $metadata = [],
        bool $generateApiKey = false
    ): array {
        try {
            // Vérifier que l'application a une base de données
            if (!$application->hasDatabase()) {
                throw new \Exception('L\'application n\'a pas de base de données configurée.');
            }

            $appDatabase = $application->appDatabase;

            // Générer organization_name si non fourni
            if (empty($organizationName)) {
                $organizationName = $this->organizationNameGenerator->generate('auto', [
                    'email' => $email,
                    'metadata' => $metadata,
                ]);
            }

            // Générer un sous-domaine unique
            $subdomain = $this->subdomainService->generateUniqueSubdomain($organizationName, $email);

            // Configurer DNS et SSL
            $dnsConfigured = $this->subdomainService->configureDNS($subdomain);
            $sslConfigured = $this->subdomainService->configureSSL($subdomain);

            // Générer clé API si nécessaire
            $apiKey = null;
            $apiSecret = null;
            if ($generateApiKey) {
                $apiKeyResult = ApiKey::generate("Onboarding - {$subdomain}", [
                    'app_name' => $application->app_name,
                    'application_id' => $application->id,
                ]);
                $apiKey = $apiKeyResult['key'];
                $apiSecret = $apiKeyResult['key']; // Le secret est la même clé pour ApiKey
            }

            // Créer l'enregistrement dans la base centrale
            $registration = OnboardingRegistration::create([
                'application_id' => $application->id,
                'app_database_id' => $appDatabase->id,
                'email' => $email,
                'organization_name' => $organizationName,
                'subdomain' => $subdomain,
                'status' => 'pending',
                'api_key' => $apiKey,
                'api_secret' => $apiKey ? \Illuminate\Support\Facades\Hash::make($apiKey) : null,
                'metadata' => $metadata,
                'dns_configured' => $dnsConfigured,
                'ssl_configured' => $sslConfigured,
            ]);

            // Insérer dans la base de données de l'application (si mot de passe disponible)
            if ($dbPlainPassword) {
                $this->insertIntoAppDatabase($appDatabase, $registration, $dbPlainPassword);
            }

            Log::info('Onboarding enregistré avec succès', [
                'uuid' => $registration->uuid,
                'subdomain' => $subdomain,
                'application_id' => $application->id,
                'email' => $email,
            ]);

            return [
                'uuid' => $registration->uuid,
                'subdomain' => $subdomain,
                'email' => $email,
                'organization_name' => $organizationName,
                'database' => $appDatabase->database_name,
                'api_key' => $apiKey,
                'api_secret' => $apiKey, // À afficher une seule fois
                'status' => $registration->status,
                'dns_configured' => $dnsConfigured,
                'ssl_configured' => $sslConfigured,
                'url' => $this->subdomainService->getSubdomainUrl($subdomain),
                'created_at' => $registration->created_at->toIso8601String(),
            ];

        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'enregistrement d\'onboarding: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Insère l'enregistrement dans la base de données de l'application
     * 
     * @param AppDatabase $appDatabase
     * @param OnboardingRegistration $registration
     * @param string|null $plainPassword Mot de passe en clair (si disponible)
     */
    protected function insertIntoAppDatabase(AppDatabase $appDatabase, OnboardingRegistration $registration, ?string $plainPassword = null): void
    {
        try {
            // Si pas de mot de passe en clair, on ne peut pas se connecter
            // Dans ce cas, on log juste l'info et on laisse l'application créer la table elle-même
            if (!$plainPassword) {
                Log::info('Mot de passe DB non disponible, l\'application devra créer la table tenants elle-même', [
                    'database' => $appDatabase->database_name,
                    'uuid' => $registration->uuid,
                ]);
                return;
            }

            // Se connecter à la base de données de l'application
            $config = [
                'driver' => 'mysql',
                'host' => $appDatabase->db_host,
                'port' => $appDatabase->db_port,
                'database' => $appDatabase->database_name,
                'username' => $appDatabase->db_username,
                'password' => $plainPassword,
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ];

            // Configurer une connexion temporaire
            config(['database.connections.app_db' => $config]);
            DB::purge('app_db');

            // Vérifier si la table tenants existe, sinon la créer
            $connection = DB::connection('app_db');
            
            // Vérifier si la table existe
            $tables = $connection->select("SHOW TABLES LIKE 'tenants'");
            
            if (empty($tables)) {
                // Créer la table tenants dans la base de l'application
                $connection->statement("
                    CREATE TABLE `tenants` (
                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        `uuid` varchar(36) NOT NULL,
                        `email` varchar(255) NOT NULL,
                        `organization_name` varchar(255) DEFAULT NULL,
                        `subdomain` varchar(255) NOT NULL,
                        `status` enum('pending','activated','cancelled') NOT NULL DEFAULT 'pending',
                        `api_key` varchar(64) DEFAULT NULL,
                        `metadata` json DEFAULT NULL,
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `tenants_uuid_unique` (`uuid`),
                        UNIQUE KEY `tenants_subdomain_unique` (`subdomain`),
                        KEY `tenants_email_index` (`email`),
                        KEY `tenants_status_index` (`status`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
            }

            // Insérer l'enregistrement
            $connection->table('tenants')->insert([
                'uuid' => $registration->uuid,
                'email' => $registration->email,
                'organization_name' => $registration->organization_name,
                'subdomain' => $registration->subdomain,
                'status' => $registration->status,
                'api_key' => $registration->api_key,
                'metadata' => json_encode($registration->metadata),
                'created_at' => $registration->created_at,
                'updated_at' => $registration->updated_at,
            ]);

            Log::info('Enregistrement inséré dans la base de l\'application', [
                'database' => $appDatabase->database_name,
                'uuid' => $registration->uuid,
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'insertion dans la base de l\'application: ' . $e->getMessage());
            // Ne pas faire échouer l'onboarding si l'insertion échoue
            // L'enregistrement dans la base centrale est déjà fait
        } finally {
            // Nettoyer la connexion
            DB::purge('app_db');
        }
    }

    /**
     * Récupère un enregistrement d'onboarding par UUID
     */
    public function getByUuid(string $uuid): ?OnboardingRegistration
    {
        return OnboardingRegistration::where('uuid', $uuid)->first();
    }

    /**
     * Met à jour le statut d'un onboarding
     */
    public function updateStatus(OnboardingRegistration $registration, string $status): bool
    {
        if (!in_array($status, ['pending', 'activated', 'cancelled', 'completed'])) {
            throw new \InvalidArgumentException("Statut invalide: {$status}");
        }

        $updateData = ['status' => $status];
        
        // Si le statut est "completed", ajouter la date de complétion
        if ($status === 'completed' && !$registration->completed_at) {
            $updateData['completed_at'] = now();
        }
        
        $registration->update($updateData);

        // Mettre à jour aussi dans la base de l'application si possible
        try {
            $appDatabase = $registration->appDatabase;
            $config = [
                'driver' => 'mysql',
                'host' => $appDatabase->db_host,
                'port' => $appDatabase->db_port,
                'database' => $appDatabase->database_name,
                'username' => $appDatabase->db_username,
                'password' => $appDatabase->db_password,
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ];

            config(['database.connections.app_db' => $config]);
            DB::purge('app_db');
            
            DB::connection('app_db')
                ->table('tenants')
                ->where('uuid', $registration->uuid)
                ->update(['status' => $status, 'updated_at' => now()]);
                
            DB::purge('app_db');
        } catch (\Exception $e) {
            Log::warning('Impossible de mettre à jour le statut dans la base de l\'application: ' . $e->getMessage());
        }

        return true;
    }
}
