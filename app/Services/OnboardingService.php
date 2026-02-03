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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use App\Models\User;
use App\Services\TenantService;
use App\Services\WebhookService;
use App\Services\ActivationService;

class OnboardingService
{
    protected $tenantService;
    protected $webhookService;
    protected $activationService;

    public function __construct(TenantService $tenantService, WebhookService $webhookService = null, ActivationService $activationService = null)
    {
        $this->tenantService = $tenantService;
        $this->webhookService = $webhookService ?? app(WebhookService::class);
        $this->activationService = $activationService ?? app(ActivationService::class);
    }

    /**
     * Traite l'onboarding pour une application externe
     * (Accepte des migrations dynamiques, une URL de callback, et un nom d'application source)
     */
    public function processExternalOnboarding(string $email, string $organizationName, string $callbackUrl = null, array $migrations = [], array $metadata = [], string $sourceAppName = null): array
    {
        // 1. Démarrer comme un onboarding standard (en passant sourceAppName via metadata temporairement ou modifiant processOnboarding)
        // Pour garder la signature propre, on passe sourceAppName à processOnboarding
        $result = $this->processOnboarding($email, $organizationName, $metadata, $sourceAppName);

        // 2. Exécuter les migrations dynamiques si présentes
        if (!empty($migrations)) {
            $this->runDynamicMigrations($migrations, $result['database']);
        }

        // 3. Envoyer le callback si demandé
        if ($callbackUrl) {
            $this->sendCallback($callbackUrl, $result);
        }

        return $result;
    }

    /**
     * Traite l'onboarding avec seulement email et organisation
     * Ne crée pas l'utilisateur admin (sera fait lors de l'activation)
     */
    public function processOnboarding(string $email, string $organizationName, array $metadata = [], string $sourceAppName = null): array
    {
        try {
            // Vérifier que l'email est unique
            $this->validateEmailUnique($email);
            
            // Vérifier que le nom de l'organisation est unique (SCOPÉ par Application)
            $this->validateOrganizationNameUnique($organizationName, $sourceAppName);
            
            // Générer un slug unique et un sous-domaine unique
            $slug = $this->generateUniqueSlug($organizationName);
            $subdomain = $this->generateUniqueSubdomain($slug);
            
            // Vérifier que le sous-domaine est unique
            $this->validateSubdomainUnique($subdomain);
            
            // Créer la base de données avec un nom unique
            $databaseName = $this->createUniqueDatabase($subdomain);
            
            // Vérifier que le nom de la base de données est unique
            $this->validateDatabaseNameUnique($databaseName);
            
            // Créer le sous-domaine
            $this->createSubdomain($subdomain);
            
            // Enregistrer la session d'onboarding (dans la base principale)
            $this->saveOnboardingSessionNew($email, $organizationName, $slug, $subdomain, $databaseName, $metadata, $sourceAppName);
            
            // Basculer vers la base du tenant
            $this->tenantService->switchToTenantDatabase($databaseName);
            
            // Exécuter les migrations dans la base du tenant
            $this->runMigrationsInTenantDatabase();
            
            // Initialiser les settings de personnalisation par défaut
            $this->initializeTenantSettings($organizationName);
            
            // Revenir à la base principale
            Config::set('database.default', 'mysql');
            DB::purge('tenant');
            
            // Créer le token d'activation
            $activationToken = $this->activationService->createActivationToken(
                $email,
                $organizationName,
                $subdomain,
                $databaseName
            );
            
            $result = [
                'subdomain' => $subdomain,
                'database' => $databaseName,
                'url' => $this->getSubdomainUrl($subdomain),
                'email' => $email,
                'organization_name' => $organizationName,
                'activation_token' => $activationToken,
                'metadata' => $metadata,
            ];

            // Déclencher le webhook d'onboarding complété
            $this->webhookService->trigger('onboarding.completed', [
                'subdomain' => $subdomain,
                'database_name' => $databaseName,
                'organization_name' => $organizationName,
                'email' => $email,
                'url' => $result['url'],
                'metadata' => $metadata,
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Erreur dans processOnboarding: ' . $e->getMessage());
            
            // Déclencher le webhook d'échec
            $this->webhookService->trigger('onboarding.failed', [
                'error' => $e->getMessage(),
                'organization_name' => $organizationName ?? null,
            ]);
            
            // S'assurer de revenir à la base principale en cas d'erreur
            Config::set('database.default', 'mysql');
            DB::purge('tenant');
            throw $e;
        }
    }

    /**
     * Enregistre la session d'onboarding avec les nouvelles données (email + organisation uniquement)
     */
    protected function saveOnboardingSessionNew(string $email, string $organizationName, string $slug, string $subdomain, string $databaseName, array $metadata = [], string $sourceAppName = null): void
    {
        try {
            // Vérifier si un enregistrement avec ce sous-domaine existe déjà
            $existing = OnboardingSession::on('mysql')->where('subdomain', $subdomain)->first();
            
            if ($existing) {
                // Mettre à jour l'enregistrement existant
                // IMPORTANT: admin_first_name et admin_last_name sont requis par la table
                // mais ne sont plus collectés dans le nouveau flux. On les met à jour seulement s'ils sont vides.
                $updateData = [
                    'session_id' => session()->getId(),
                    'organization_name' => $organizationName,
                    'slug' => $slug,
                    'admin_email' => $email,
                    'database_name' => $databaseName,
                    'status' => 'pending_activation', // Statut en attente d'activation
                    'completed_at' => null,
                    'metadata' => $metadata,
                    'source_app_name' => $sourceAppName, // Sauvegarde de l'app appelante
                ];
                
                // Ajouter les champs requis seulement s'ils sont vides
                if (empty($existing->admin_first_name)) {
                    $updateData['admin_first_name'] = 'Admin';
                }
                if (empty($existing->admin_last_name)) {
                    $updateData['admin_last_name'] = 'User';
                }
                
                $existing->update($updateData);
                Log::info("Session d'onboarding mise à jour pour: {$subdomain} (ID: {$existing->id})");
            } else {
                // Créer un nouvel enregistrement
                // IMPORTANT: admin_first_name et admin_last_name sont requis par la table
                // mais ne sont plus collectés dans le nouveau flux. On utilise des valeurs par défaut.
                $session = OnboardingSession::on('mysql')->create([
                    'session_id' => session()->getId(),
                    'organization_name' => $organizationName,
                    'slug' => $slug,
                    'admin_first_name' => 'Admin', // Valeur par défaut, sera mis à jour lors de l'activation
                    'admin_last_name' => 'User', // Valeur par défaut, sera mis à jour lors de l'activation
                    'admin_email' => $email,
                    'subdomain' => $subdomain,
                    'database_name' => $databaseName,
                    'status' => 'pending_activation', // Statut en attente d'activation
                    'completed_at' => null,
                    'metadata' => $metadata,
                    'source_app_name' => $sourceAppName, // Sauvegarde de l'app appelante
                ]);
                Log::info("Nouvelle session d'onboarding créée pour: {$subdomain} (ID: {$session->id})");
            }
            
            // Nettoyer le cache pour ce tenant
            $this->tenantService->clearTenantCache($subdomain);
            
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'enregistrement de la session: ' . $e->getMessage(), [
                'subdomain' => $subdomain,
            ]);
            
            try {
                $this->tenantService->clearTenantCache($subdomain);
            } catch (\Exception $cacheException) {
                // Ignorer les erreurs de cache
            }
            
            throw $e;
        }
    }

    /**
     * Valide que l'email est acceptable (désormais autorisé pour plusieurs sous-domaines)
     */
    protected function validateEmailUnique(string $email): void
    {
        // On autorise désormais le même email pour plusieurs sous-domaines.
        // On pourrait ajouter une vérification pour éviter trop de créations par minute si besoin.
        
        Log::info("Utilisation de l'email pour onboarding: {$email}");
    }
    
    /**
     * Valide que le sous-domaine est unique
     */
    protected function validateSubdomainUnique(string $subdomain): void
    {
        $exists = OnboardingSession::on('mysql')
            ->where('subdomain', $subdomain)
            ->exists();
        
        if ($exists) {
            throw new \Exception("Le sous-domaine '{$subdomain}' est déjà utilisé. Veuillez réessayer.");
        }
    }
    
    /**
     * Valide que le nom de la base de données est unique
     */
    protected function validateDatabaseNameUnique(string $databaseName): void
    {
        $exists = OnboardingSession::on('mysql')
            ->where('database_name', $databaseName)
            ->exists();
        
        if ($exists) {
            throw new \Exception("Le nom de base de données '{$databaseName}' est déjà utilisé. Veuillez réessayer.");
        }
    }
    
    /**
     * Valide que le nom de l'organisation est unique
     * Si sourceAppName est fourni, l'unicité est vérifiée UNIQUEMENT au sein de cette app.
     */
    protected function validateOrganizationNameUnique(string $organizationName, string $sourceAppName = null): void
    {
        $query = OnboardingSession::on('mysql')
            ->where('organization_name', $organizationName);
            
        // Si une app source est définie, on filtre aussi par elle
        if ($sourceAppName) {
            $query->where('source_app_name', $sourceAppName);
        } else {
            // Si pas d'app source (legacy/interne), on vérifie les entrées sans app source
            // OU on garde le comportement global strict si on préfère.
            // Pour l'instant, disons que NULL ne conflit pas avec "AppA".
            $query->whereNull('source_app_name');
        }
            
        $exists = $query->exists();
        
        if ($exists) {
            $msg = "Une organisation avec le nom '{$organizationName}' existe déjà";
            if ($sourceAppName) {
                $msg .= " pour l'application {$sourceAppName}";
            }
            $msg .= ". Veuillez choisir un autre nom.";
            throw new \Exception($msg);
        }
    }
    
    protected function generateUniqueSlug(string $organizationName): string
    {
        // Nettoyer et formater le nom de l'organisation
        $slug = Str::slug($organizationName, '-', 'fr');
        
        // Limiter la longueur à 30 caractères pour éviter des slugs trop longs
        $slug = substr($slug, 0, 30);
        
        // Supprimer les tirets en début et fin
        $slug = trim($slug, '-');
        
        // Si le slug est vide après nettoyage, utiliser un nom par défaut
        if (empty($slug)) {
            $slug = 'org';
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
        $baseDatabaseName = 'akasigroup_' . $subdomain;
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
        $baseDomain = config('app.subdomain_base_domain', 'akasigroup.local');
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
        $baseDomain = config('app.subdomain_base_domain', 'akasigroup.local');
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

    protected function initializeTenantSettings(string $organizationName): void
    {
        try {
            $customizationService = app(\App\Services\TenantCustomizationService::class);
            $customizationService->initializeDefaults($organizationName);
        } catch (\Exception $e) {
            Log::warning("Erreur lors de l'initialisation des settings: " . $e->getMessage());
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
                $baseDomain = config('app.subdomain_base_domain', 'akasigroup.local');
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
    protected function runDynamicMigrations(array $migrations, string $databaseName): void
    {
        $tempPath = storage_path('app/temp/migrations/' . uniqid());
        
        try {
            if (!File::exists($tempPath)) {
                File::makeDirectory($tempPath, 0755, true);
            }

            foreach ($migrations as $migration) {
                // Validation simple
                if (!isset($migration['filename']) || !isset($migration['content'])) {
                    continue;
                }
                
                // Ajouter le timestamp à chaque fichier pour garantir l'ordre
                $filename = $migration['filename'];
                if (!preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_/', $filename)) {
                    $filename = date('Y_m_d_His_') . $filename;
                }

                File::put($tempPath . '/' . $filename, $migration['content']);
            }

            Log::info("Exécution des migrations dynamiques pour {$databaseName} depuis {$tempPath}");

            // Exécuter les migrations sur la base tenant
            // Note: On doit configurer temporairement 'tenant' connection si ce n'est pas déjà fait,
            // mais ici on suppose que processOnboarding a déjà switché ou qu'on le fait explicitement.
            // processOnboarding() reset à 'mysql' à la fin, donc on doit reswitcher.
            $this->tenantService->switchToTenantDatabase($databaseName);

            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => str_replace(base_path(), '', $tempPath),
                '--force' => true,
            ]);

            Log::info("Migrations dynamiques terminées");

        } catch (\Exception $e) {
            Log::error("Erreur migrations dynamiques: " . $e->getMessage());
            // On throw l'exception pour que l'appelant sache qu'il y a eu un souci
            throw $e;
        } finally {
            // Nettoyage: supprimer le dossier temporaire
            if (File::exists($tempPath)) {
                File::deleteDirectory($tempPath);
            }
            
            // Revenir à la base core par sécurité
            Config::set('database.default', 'mysql');
            DB::purge('tenant');
        }
    }

    protected function sendCallback(string $url, array $data): void
    {
        try {
            // Éviter le deadlock en local si l'URL pointe vers le même serveur (single-threaded)
            if (app()->environment('local') && (str_contains($url, 'localhost') || str_contains($url, '127.0.0.1'))) {
                Log::info("[OnboardingService] Callback passÃ©e en local pour Ã©viter le deadlock: {$url}");
                return;
            }

            Log::info("Envoi du callback vers {$url}");
            
            Http::timeout(10)
                ->retry(2, 100)
                ->post($url, $data);
                
        } catch (\Exception $e) {
            Log::warning("Echec de l'envoi du callback: " . $e->getMessage());
            // On ne throw PAS l'exception ici car le tenant est crÃ©Ã©, c'est juste la notif qui a Ã©chouÃ©.
        }
    }
}
