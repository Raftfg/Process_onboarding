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
            // Générer le sous-domaine
            $subdomain = $this->generateSubdomain($data['step1']['hospital_name']);
            
            // Créer la base de données
            $databaseName = $this->createDatabase($subdomain);
            
            // Créer le sous-domaine
            $this->createSubdomain($subdomain);
            
            // Enregistrer la session d'onboarding (dans la base principale)
            $this->saveOnboardingSession($data, $subdomain, $databaseName);
            
            // Basculer vers la base du tenant
            $this->tenantService->switchToTenantDatabase($databaseName);
            
            // Exécuter les migrations dans la base du tenant
            $this->runMigrationsInTenantDatabase();
            
            // Créer l'utilisateur administrateur dans la base du tenant
            $user = $this->createAdminUser($data['step2'], $data['step1']['hospital_name']);
            
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

    protected function saveOnboardingSession(array $data, string $subdomain, string $databaseName): void
    {
        try {
            OnboardingSession::create([
                'session_id' => session()->getId(),
                'hospital_name' => $data['step1']['hospital_name'],
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
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'enregistrement de la session: ' . $e->getMessage());
            // Ne pas faire échouer le processus si l'enregistrement échoue
        }
    }

    protected function generateSubdomain(string $hospitalName): string
    {
        $baseDomain = config('app.subdomain_base_domain', 'medkey.local');
        $slug = Str::slug($hospitalName);
        $subdomain = strtolower($slug);
        
        // Vérifier l'unicité (dans un vrai projet, vérifier en base de données)
        // Pour l'instant, on ajoute un timestamp pour garantir l'unicité
        $subdomain = $subdomain . '-' . time();
        
        return $subdomain;
    }

    protected function createDatabase(string $subdomain): string
    {
        $databaseName = 'medkey_' . $subdomain;
        $rootUsername = config('database.connections.mysql.root_username', env('DB_ROOT_USERNAME', 'root'));
        $rootPassword = config('database.connections.mysql.root_password', env('DB_ROOT_PASSWORD', ''));
        
        try {
            // Se connecter à MySQL sans spécifier de base de données
            $pdo = new \PDO(
                "mysql:host=" . config('database.connections.mysql.host'),
                $rootUsername,
                $rootPassword
            );
            
            // Créer la base de données
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            Log::info("Base de données créée: {$databaseName}");
            
            return $databaseName;
        } catch (\PDOException $e) {
            Log::error("Erreur création base de données: " . $e->getMessage());
            throw new \Exception("Impossible de créer la base de données: " . $e->getMessage());
        }
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
        // Utiliser la fonction helper pour générer l'URL avec sous-domaine
        return subdomain_url($subdomain, '/dashboard');
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
            
            // Créer la table users
            $connection->statement("
                CREATE TABLE IF NOT EXISTS `users` (
                    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                    `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                    `email_verified_at` timestamp NULL DEFAULT NULL,
                    `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                    `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `created_at` timestamp NULL DEFAULT NULL,
                    `updated_at` timestamp NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `users_email_unique` (`email`)
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

    protected function sendWelcomeEmail(array $adminData, string $subdomain): void
    {
        try {
            $url = $this->getSubdomainUrl($subdomain);
            
            Mail::to($adminData['admin_email'])->send(
                new OnboardingWelcomeMail($adminData, $subdomain, $url)
            );
            
            Log::info("Email de bienvenue envoyé à: {$adminData['admin_email']}");
        } catch (\Exception $e) {
            Log::error("Erreur envoi email: " . $e->getMessage());
            // Ne pas faire échouer tout le processus si l'email échoue
        }
    }
}
