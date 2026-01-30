<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Config;
use App\Mail\OnboardingWelcomeMail;
use App\Models\OnboardingSession;
use App\Models\User;
use App\Services\TenantService;
use App\Services\WebhookService;

class OnboardingService
{
    protected $tenantService;
    protected $webhookService;

    public function __construct(TenantService $tenantService, WebhookService $webhookService = null)
    {
        $this->tenantService = $tenantService;
        $this->webhookService = $webhookService ?? app(WebhookService::class);
    }

    public function processOnboarding(array $data)
    {
        try {
            // Vérifier que le nom de l'hôpital est unique
            $this->validateHospitalNameUnique($data['step1']['hospital_name']);
            
            // Générer un slug unique et un sous-domaine unique
            $slug = $this->generateUniqueSlug($data['step1']['hospital_name']);
            $subdomain = $this->generateUniqueSubdomain($slug);
            
            // Créer la base de données avec un nom unique
            $databaseName = $this->createUniqueDatabase($subdomain);
            
            // Créer le sous-domaine
            $this->createSubdomain($subdomain);
            
            // Enregistrer la session d'onboarding (dans la base principale)
            $this->saveOnboardingSession($data, $slug, $subdomain, $databaseName);
            
            // Basculer vers la base du tenant
            $this->tenantService->switchToTenantDatabase($databaseName);
            
            // Exécuter les migrations dans la base du tenant
            $this->runMigrationsInTenantDatabase();
            
            // Créer l'utilisateur administrateur dans la base du tenant
            $user = $this->createAdminUser($data['step2'], $data['step1']['hospital_name']);
            
            // Initialiser les settings de personnalisation par défaut
            $this->initializeTenantSettings($data['step1']['hospital_name']);
            
            // Revenir à la base principale
            Config::set('database.default', 'mysql');
            DB::purge('tenant');
            
            // Envoyer l'email
            $this->sendWelcomeEmail($data['step2'], $subdomain);
            
            $result = [
                'subdomain' => $subdomain,
                'database' => $databaseName,
                'url' => $this->getSubdomainUrl($subdomain),
                'admin_email' => $data['step2']['admin_email'],
                'user_id' => $user->id
            ];

            // Déclencher le webhook d'onboarding complété
            $this->webhookService->trigger('onboarding.completed', [
                'subdomain' => $subdomain,
                'database_name' => $databaseName,
                'hospital_name' => $data['step1']['hospital_name'],
                'admin_email' => $data['step2']['admin_email'],
                'url' => $result['url'],
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Erreur dans processOnboarding: ' . $e->getMessage());
            
            // Déclencher le webhook d'échec
            $this->webhookService->trigger('onboarding.failed', [
                'error' => $e->getMessage(),
                'hospital_name' => $data['step1']['hospital_name'] ?? null,
            ]);
            
            // S'assurer de revenir à la base principale en cas d'erreur
            Config::set('database.default', 'mysql');
            DB::purge('tenant');
            throw $e;
        }
    }

    protected function saveOnboardingSession(array $data, string $slug, string $subdomain, string $databaseName): void
    {
        try {
            // Vérifier si un enregistrement avec ce sous-domaine existe déjà
            $existing = OnboardingSession::on('mysql')->where('subdomain', $subdomain)->first();
            
            if ($existing) {
                // Mettre à jour l'enregistrement existant
                $existing->update([
                    'session_id' => session()->getId(),
                    'hospital_name' => $data['step1']['hospital_name'],
                    'slug' => $slug,
                    'hospital_address' => $data['step1']['hospital_address'] ?? null,
                    'hospital_phone' => $data['step1']['hospital_phone'] ?? null,
                    'hospital_email' => $data['step1']['hospital_email'] ?? null,
                    'admin_first_name' => $data['step2']['admin_first_name'],
                    'admin_last_name' => $data['step2']['admin_last_name'],
                    'admin_email' => $data['step2']['admin_email'],
                    'database_name' => $databaseName,
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
                Log::info("Session d'onboarding mise à jour pour: {$subdomain} (ID: {$existing->id})");
            } else {
                // Créer un nouvel enregistrement
                $session = OnboardingSession::on('mysql')->create([
                    'session_id' => session()->getId(),
                    'hospital_name' => $data['step1']['hospital_name'],
                    'slug' => $slug,
                    'hospital_address' => $data['step1']['hospital_address'] ?? null,
                    'hospital_phone' => $data['step1']['hospital_phone'] ?? null,
                    'hospital_email' => $data['step1']['hospital_email'] ?? null,
                    'admin_first_name' => $data['step2']['admin_first_name'],
                    'admin_last_name' => $data['step2']['admin_last_name'],
                    'admin_email' => $data['step2']['admin_email'],
                    'subdomain' => $subdomain,
                    'database_name' => $databaseName,
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
                Log::info("Nouvelle session d'onboarding créée pour: {$subdomain} (ID: {$session->id})");
            }
            
            // Nettoyer le cache pour ce tenant
            $this->tenantService->clearTenantCache($subdomain);
            
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'enregistrement de la session: ' . $e->getMessage(), [
                'subdomain' => $subdomain,
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
            // Ne pas faire échouer le processus si l'enregistrement échoue
            // mais essayer quand même de nettoyer le cache
            try {
                $this->tenantService->clearTenantCache($subdomain);
            } catch (\Exception $cacheException) {
                // Ignorer les erreurs de cache
            }
        }
    }

    /**
     * Valide que le nom de l'hôpital est unique
     */
    protected function validateHospitalNameUnique(string $hospitalName): void
    {
        $exists = OnboardingSession::on('mysql')
            ->where('hospital_name', $hospitalName)
            ->exists();
        
        if ($exists) {
            throw new \Exception("Un hôpital avec le nom '{$hospitalName}' existe déjà. Veuillez choisir un autre nom.");
        }
    }
    
    /**
     * Génère un slug unique basé sur le nom de l'hôpital
     */
    protected function generateUniqueSlug(string $hospitalName): string
    {
        // Nettoyer et formater le nom de l'hôpital
        $slug = Str::slug($hospitalName, '-', 'fr');
        
        // Limiter la longueur à 30 caractères pour éviter des slugs trop longs
        $slug = substr($slug, 0, 30);
        
        // Supprimer les tirets en début et fin
        $slug = trim($slug, '-');
        
        // Si le slug est vide après nettoyage, utiliser un nom par défaut
        if (empty($slug)) {
            $slug = 'hopital';
        }
        
        // Générer un slug de base
        $baseSlug = $slug;
        $uniqueSlug = $baseSlug;
        $counter = 1;
        $maxAttempts = 100;
        
        // Vérifier l'unicité et ajouter un suffixe si nécessaire
        while ($this->slugExists($uniqueSlug) && $counter < $maxAttempts) {
            $uniqueSlug = $baseSlug . '-' . $counter;
            $counter++;
        }
        
        // Si on a atteint le maximum de tentatives, utiliser un timestamp
        if ($counter >= $maxAttempts) {
            $uniqueSlug = $baseSlug . '-' . time();
        }
        
        return $uniqueSlug;
    }
    
    /**
     * Vérifie si un slug existe déjà
     */
    protected function slugExists(string $slug): bool
    {
        return OnboardingSession::on('mysql')
            ->where('slug', $slug)
            ->exists();
    }
    
    /**
     * Génère un sous-domaine unique basé sur le slug
     */
    protected function generateUniqueSubdomain(string $slug): string
    {
        // Générer un sous-domaine de base à partir du slug
        $baseSubdomain = $slug;
        $subdomain = $baseSubdomain;
        $counter = 1;
        $maxAttempts = 100;
        
        // Vérifier l'unicité et ajouter un suffixe si nécessaire
        while ($this->subdomainExists($subdomain) && $counter < $maxAttempts) {
            $subdomain = $baseSubdomain . '-' . $counter;
            $counter++;
        }
        
        // Si on a atteint le maximum de tentatives, utiliser un timestamp
        if ($counter >= $maxAttempts) {
            $subdomain = $baseSubdomain . '-' . time();
        }
        
        return $subdomain;
    }
    
    /**
     * Vérifie si un sous-domaine existe déjà
     */
    protected function subdomainExists(string $subdomain): bool
    {
        return OnboardingSession::on('mysql')
            ->where('subdomain', $subdomain)
            ->exists();
    }

    /**
     * Crée une base de données avec un nom unique
     */
    protected function createUniqueDatabase(string $subdomain): string
    {
        $baseDatabaseName = 'medkey_' . $subdomain;
        $databaseName = $baseDatabaseName;
        $counter = 1;
        $maxAttempts = 100;
        
        // Vérifier l'unicité du nom de la base de données
        while ($this->databaseNameExists($databaseName) && $counter < $maxAttempts) {
            $databaseName = $baseDatabaseName . '_' . $counter;
            $counter++;
        }
        
        // Si on a atteint le maximum de tentatives, utiliser un timestamp
        if ($counter >= $maxAttempts) {
            $databaseName = $baseDatabaseName . '_' . time();
        }
        
        $rootUsername = config('database.connections.mysql.root_username', env('DB_ROOT_USERNAME', 'root'));
        $rootPassword = config('database.connections.mysql.root_password', env('DB_ROOT_PASSWORD', ''));
        
        try {
            // Se connecter à MySQL sans spécifier de base de données
            $pdo = new \PDO(
                "mysql:host=" . config('database.connections.mysql.host'),
                $rootUsername,
                $rootPassword
            );
            
            // Vérifier si la base de données existe déjà dans MySQL
            $stmt = $pdo->query("SHOW DATABASES LIKE '{$databaseName}'");
            if ($stmt->rowCount() > 0) {
                // Si la base existe déjà, générer un nouveau nom
                $databaseName = $baseDatabaseName . '_' . time();
            }
            
            // Créer la base de données
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            Log::info("Base de données créée: {$databaseName}");
            
            return $databaseName;
        } catch (\PDOException $e) {
            Log::error("Erreur création base de données: " . $e->getMessage());
            throw new \Exception("Impossible de créer la base de données: " . $e->getMessage());
        }
    }
    
    /**
     * Vérifie si un nom de base de données existe déjà dans onboarding_sessions
     */
    protected function databaseNameExists(string $databaseName): bool
    {
        return OnboardingSession::on('mysql')
            ->where('database_name', $databaseName)
            ->exists();
    }

    protected function createSubdomain(string $subdomain): void
    {
        $baseDomain = config('app.subdomain_base_domain', 'medkey.local');
        $webRoot = config('app.subdomain_web_root', '/var/www/html');
        
        // Dans un environnement de production, vous devriez:
        // 1. Créer un vhost Apache/Nginx
        // 2. Ajouter une entrée DNS
        // 3. Créer un répertoire pour le sous-domaine
        
        // Pour cette démo, on simule la création
        Log::info("Sous-domaine créé: {$subdomain}.{$baseDomain}");
        
        // Exemple de création de vhost (à adapter selon votre environnement)
        // $this->createApacheVhost($subdomain, $baseDomain, $webRoot);
    }

    protected function getSubdomainUrl(string $subdomain): string
    {
        // En développement local, utiliser le format sous-domaine.localhost:8000
        // localhost fonctionne nativement sans configuration supplémentaire
        if (config('app.env') === 'local') {
            return "http://{$subdomain}.localhost:8000";
        }
        
        // En production, utiliser le vrai sous-domaine
        $baseDomain = config('app.subdomain_base_domain', 'medkey.local');
        return "https://{$subdomain}.{$baseDomain}";
    }

    protected function runMigrationsInTenantDatabase(): void
    {
        try {
            // Exécuter les migrations dans la base du tenant
            // Utiliser DB directement pour créer les tables nécessaires
            $connection = DB::connection('tenant');
            
            // Créer la table sessions
            $connection->statement("
                CREATE TABLE IF NOT EXISTS `sessions` (
                    `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                    `user_id` bigint(20) unsigned DEFAULT NULL,
                    `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `user_agent` text COLLATE utf8mb4_unicode_ci,
                    `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
                    `last_activity` int(11) NOT NULL,
                    PRIMARY KEY (`id`),
                    KEY `sessions_user_id_index` (`user_id`),
                    KEY `sessions_last_activity_index` (`last_activity`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
            
            // Créer la table users avec tous les champs
            $connection->statement("
                CREATE TABLE IF NOT EXISTS `users` (
                    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                    `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                    `email_verified_at` timestamp NULL DEFAULT NULL,
                    `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                    `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `role` enum('admin','user') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
                    `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `last_login_at` timestamp NULL DEFAULT NULL,
                    `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
                    `created_at` timestamp NULL DEFAULT NULL,
                    `updated_at` timestamp NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `users_email_unique` (`email`),
                    KEY `users_role_index` (`role`),
                    KEY `users_status_index` (`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
            
            // Vérifier et ajouter les colonnes manquantes si la table existe déjà
            $columns = $connection->select("SHOW COLUMNS FROM `users`");
            $columnNames = array_column($columns, 'Field');
            
            if (!in_array('role', $columnNames)) {
                $connection->statement("ALTER TABLE `users` ADD COLUMN `role` enum('admin','user') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user' AFTER `email`");
                $connection->statement("ALTER TABLE `users` ADD INDEX `users_role_index` (`role`)");
            }
            if (!in_array('avatar', $columnNames)) {
                $connection->statement("ALTER TABLE `users` ADD COLUMN `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `role`");
            }
            if (!in_array('phone', $columnNames)) {
                $connection->statement("ALTER TABLE `users` ADD COLUMN `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `avatar`");
            }
            if (!in_array('last_login_at', $columnNames)) {
                $connection->statement("ALTER TABLE `users` ADD COLUMN `last_login_at` timestamp NULL DEFAULT NULL AFTER `phone`");
            }
            if (!in_array('status', $columnNames)) {
                $connection->statement("ALTER TABLE `users` ADD COLUMN `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active' AFTER `last_login_at`");
                $connection->statement("ALTER TABLE `users` ADD INDEX `users_status_index` (`status`)");
            }
            if (!in_array('password_changed_at', $columnNames)) {
                $connection->statement("ALTER TABLE `users` ADD COLUMN `password_changed_at` timestamp NULL DEFAULT NULL AFTER `password`");
            }
            
            // Créer la table activities
            $connection->statement("
                CREATE TABLE IF NOT EXISTS `activities` (
                    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    `user_id` bigint(20) unsigned NOT NULL,
                    `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                    `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
                    `metadata` json DEFAULT NULL,
                    `created_at` timestamp NULL DEFAULT NULL,
                    `updated_at` timestamp NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    KEY `activities_user_id_index` (`user_id`),
                    KEY `activities_created_at_index` (`created_at`),
                    KEY `activities_type_index` (`type`),
                    CONSTRAINT `activities_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
            
            // Créer la table notifications
            $connection->statement("
                CREATE TABLE IF NOT EXISTS `notifications` (
                    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    `user_id` bigint(20) unsigned NOT NULL,
                    `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                    `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                    `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
                    `read_at` timestamp NULL DEFAULT NULL,
                    `data` json DEFAULT NULL,
                    `created_at` timestamp NULL DEFAULT NULL,
                    `updated_at` timestamp NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    KEY `notifications_user_id_index` (`user_id`),
                    KEY `notifications_read_at_index` (`read_at`),
                    KEY `notifications_type_index` (`type`),
                    CONSTRAINT `notifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
            
            // Créer la table tenant_settings
            $connection->statement("
                CREATE TABLE IF NOT EXISTS `tenant_settings` (
                    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                    `value` json NOT NULL,
                    `group` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
                    `created_at` timestamp NULL DEFAULT NULL,
                    `updated_at` timestamp NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `tenant_settings_key_unique` (`key`),
                    KEY `tenant_settings_group_index` (`group`),
                    KEY `tenant_settings_group_key_index` (`group`, `key`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
            
            Log::info("Migrations exécutées dans la base du tenant");
        } catch (\Exception $e) {
            Log::error("Erreur lors de l'exécution des migrations: " . $e->getMessage());
            throw new \Exception("Impossible d'exécuter les migrations: " . $e->getMessage());
        }
    }

    protected function createAdminUser(array $adminData, string $hospitalName): User
    {
        try {
            // Vérifier que nous sommes bien sur la base du tenant
            $currentDatabase = DB::connection()->getDatabaseName();
            Log::info("Création utilisateur dans la base: {$currentDatabase}");
            
            // Vérifier si l'utilisateur existe déjà dans la base du tenant
            $user = User::where('email', $adminData['admin_email'])->first();
            
            if (!$user) {
                // Créer le nouvel utilisateur dans la base du tenant
                $user = User::create([
                    'name' => $adminData['admin_first_name'] . ' ' . $adminData['admin_last_name'],
                    'email' => $adminData['admin_email'],
                    'password' => Hash::make($adminData['admin_password']),
                    'email_verified_at' => now(),
                    'role' => 'admin',
                    'status' => 'active',
                ]);
                
                Log::info("Utilisateur administrateur créé dans la base du tenant: {$adminData['admin_email']}");
            } else {
                Log::info("Utilisateur existe déjà: {$adminData['admin_email']}");
            }
            
            return $user;
        } catch (\Exception $e) {
            Log::error("Erreur création utilisateur: " . $e->getMessage());
            throw new \Exception("Impossible de créer l'utilisateur: " . $e->getMessage());
        }
    }

    /**
     * Initialiser les settings de personnalisation par défaut
     */
    protected function initializeTenantSettings(string $organizationName): void
    {
        try {
            $customizationService = app(\App\Services\TenantCustomizationService::class);
            $customizationService->initializeDefaults($organizationName);
            
            Log::info("Settings de personnalisation initialisés pour: {$organizationName}");
        } catch (\Exception $e) {
            Log::warning("Erreur lors de l'initialisation des settings: " . $e->getMessage());
            // Ne pas faire échouer le processus si les settings échouent
        }
    }

    protected function sendWelcomeEmail(array $adminData, string $subdomain): void
    {
        try {
            // Construire l'URL de login au lieu de l'URL de base
            if (config('app.env') === 'local') {
                $port = parse_url(config('app.url', 'http://localhost:8000'), PHP_URL_PORT) ?? '8000';
                $loginUrl = "http://{$subdomain}.localhost:{$port}/login";
            } else {
                $baseDomain = config('app.subdomain_base_domain', 'medkey.local');
                $loginUrl = "https://{$subdomain}.{$baseDomain}/login";
            }
            
            // Vérifier que la configuration mail est correcte
            $mailDriver = config('mail.default');
            if (!$mailDriver) {
                Log::warning("Configuration mail manquante. Email non envoyé à: {$adminData['admin_email']}");
                return;
            }
            
            // Envoyer l'email
            Mail::to($adminData['admin_email'])->send(
                new OnboardingWelcomeMail($adminData, $subdomain, $loginUrl)
            );
            
            Log::info("Email de bienvenue envoyé à: {$adminData['admin_email']}", [
                'email' => $adminData['admin_email'],
                'subdomain' => $subdomain,
                'url' => $loginUrl
            ]);
        } catch (\Exception $e) {
            Log::error("Erreur envoi email: " . $e->getMessage(), [
                'email' => $adminData['admin_email'] ?? 'unknown',
                'subdomain' => $subdomain,
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
            // Ne pas faire échouer tout le processus si l'email échoue
        }
    }
}
