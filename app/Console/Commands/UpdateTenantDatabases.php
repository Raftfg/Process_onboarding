<?php

namespace App\Console\Commands;

use App\Models\OnboardingSession;
use App\Services\TenantService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateTenantDatabases extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenants:update-databases';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Met à jour toutes les bases de données de tenant avec les nouvelles tables et colonnes';

    protected $tenantService;

    public function __construct(TenantService $tenantService)
    {
        parent::__construct();
        $this->tenantService = $tenantService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Mise à jour des bases de données de tenant...');

        // Récupérer toutes les sessions d'onboarding complétées
        $sessions = OnboardingSession::on('mysql')
            ->where('status', 'completed')
            ->whereNotNull('database_name')
            ->get();

        $this->info("Trouvé {$sessions->count()} tenant(s) à mettre à jour.");

        $updated = 0;
        $failed = 0;

        foreach ($sessions as $session) {
            try {
                $this->info("Mise à jour de la base: {$session->database_name} (subdomain: {$session->subdomain})");
                
                $this->updateTenantDatabase($session->database_name);
                
                $updated++;
                $this->info("✓ Base {$session->database_name} mise à jour avec succès");
            } catch (\Exception $e) {
                $failed++;
                $this->error("✗ Erreur pour {$session->database_name}: " . $e->getMessage());
                Log::error("Erreur lors de la mise à jour de la base {$session->database_name}: " . $e->getMessage());
            }
        }

        $this->info("\nRésumé:");
        $this->info("  ✓ Mis à jour: {$updated}");
        $this->info("  ✗ Échecs: {$failed}");
    }

    protected function updateTenantDatabase(string $databaseName): void
    {
        // Sauvegarder la connexion par défaut
        $defaultConnection = config('database.default');
        
        // Configurer la connexion tenant
        $defaultConfig = config('database.connections.mysql');
        config([
            'database.connections.tenant' => [
                'driver' => 'mysql',
                'host' => $defaultConfig['host'],
                'port' => $defaultConfig['port'],
                'database' => $databaseName,
                'username' => $defaultConfig['username'],
                'password' => $defaultConfig['password'],
                'charset' => $defaultConfig['charset'],
                'collation' => $defaultConfig['collation'],
            ]
        ]);

        DB::purge('tenant');
        $connection = DB::connection('tenant');

        // Vérifier et ajouter les colonnes manquantes à la table users
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

        // Mettre à jour les utilisateurs existants sans role/status
        $connection->statement("UPDATE `users` SET `role` = 'admin', `status` = 'active' WHERE `role` IS NULL OR `status` IS NULL");

        // Créer la table activities si elle n'existe pas
        $tables = $connection->select("SHOW TABLES LIKE 'activities'");
        if (empty($tables)) {
            $connection->statement("
                CREATE TABLE `activities` (
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
        }

        // Créer la table notifications si elle n'existe pas
        $tables = $connection->select("SHOW TABLES LIKE 'notifications'");
        if (empty($tables)) {
            $connection->statement("
                CREATE TABLE `notifications` (
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
        }

        // Créer la table tenant_settings si elle n'existe pas
        $tables = $connection->select("SHOW TABLES LIKE 'tenant_settings'");
        if (empty($tables)) {
            $connection->statement("
                CREATE TABLE `tenant_settings` (
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
            
            // Initialiser les settings par défaut directement en base
            $defaultSettings = [
                ['key' => 'primary_color', 'value' => json_encode('#667eea'), 'group' => 'branding'],
                ['key' => 'secondary_color', 'value' => json_encode('#764ba2'), 'group' => 'branding'],
                ['key' => 'accent_color', 'value' => json_encode('#10b981'), 'group' => 'branding'],
                ['key' => 'background_color', 'value' => json_encode('#f5f7fa'), 'group' => 'branding'],
                ['key' => 'welcome_message', 'value' => json_encode('Bienvenue sur votre espace'), 'group' => 'layout'],
                ['key' => 'dashboard_widgets', 'value' => json_encode(['stats' => true, 'activities' => true, 'calendar' => true, 'quick_actions' => true]), 'group' => 'layout'],
                ['key' => 'grid_columns', 'value' => json_encode(3), 'group' => 'layout'],
                ['key' => 'spacing', 'value' => json_encode('normal'), 'group' => 'layout'],
                ['key' => 'items', 'value' => json_encode([
                    ['key' => 'dashboard', 'label' => 'Tableau de bord', 'icon' => '📊', 'enabled' => true, 'order' => 1],
                    ['key' => 'users', 'label' => 'Utilisateurs', 'icon' => '👥', 'enabled' => true, 'order' => 2],
                    ['key' => 'activities', 'label' => 'Activités', 'icon' => '📝', 'enabled' => true, 'order' => 3],
                    ['key' => 'reports', 'label' => 'Rapports', 'icon' => '📈', 'enabled' => true, 'order' => 4],
                    ['key' => 'settings', 'label' => 'Paramètres', 'icon' => '⚙️', 'enabled' => true, 'order' => 5],
                    ['key' => 'customization', 'label' => 'Personnalisation', 'icon' => '🎨', 'enabled' => true, 'order' => 6],
                ]), 'group' => 'menu'],
            ];
            
            foreach ($defaultSettings as $setting) {
                $connection->table('tenant_settings')->insert([
                    'key' => $setting['key'],
                    'value' => $setting['value'],
                    'group' => $setting['group'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Restaurer la connexion par défaut
        config(['database.default' => $defaultConnection]);
        DB::purge('tenant');
    }
}
