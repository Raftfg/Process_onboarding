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
use App\Models\Tenant;
use App\Models\Tenant\User as TenantUser;
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
            
            // Créer le tenant dans la base principale
            $tenant = $this->createTenant($data, $subdomain, $databaseName);
            
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

    /**
     * Crée un tenant dans la base principale
     */
    protected function createTenant(array $data, string $subdomain, string $databaseName): Tenant
    {
        try {
            $tenant = $this->tenantService->createTenant([
                'subdomain' => $subdomain,
                'database_name' => $databaseName,
                'name' => $data['step1']['hospital_name'],
                'email' => $data['step1']['hospital_email'] ?? $data['step2']['admin_email'],
                'phone' => $data['step1']['hospital_phone'] ?? null,
                'address' => $data['step1']['hospital_address'] ?? null,
                'status' => 'active',
            ]);
            
            Log::info("Tenant créé: {$subdomain}");
            return $tenant;
        } catch (\Exception $e) {
            Log::error("Erreur création tenant: " . $e->getMessage());
            throw new \Exception("Impossible de créer le tenant: " . $e->getMessage());
        }
    }

    protected function runMigrationsInTenantDatabase(): void
    {
        try {
            // Récupérer le nom de la base de données actuelle
            $databaseName = DB::connection()->getDatabaseName();
            
            // Utiliser TenantService pour exécuter les migrations
            $this->tenantService->runTenantMigrations($databaseName);
            
            Log::info("Migrations exécutées dans la base du tenant: {$databaseName}");
        } catch (\Exception $e) {
            Log::error("Erreur lors de l'exécution des migrations: " . $e->getMessage());
            throw new \Exception("Impossible d'exécuter les migrations: " . $e->getMessage());
        }
    }

    protected function createAdminUser(array $adminData, string $hospitalName): TenantUser
    {
        try {
            // Vérifier que nous sommes bien sur la base du tenant
            $currentDatabase = DB::connection()->getDatabaseName();
            Log::info("Création utilisateur dans la base: {$currentDatabase}");
            
            // Vérifier si l'utilisateur existe déjà dans la base du tenant
            $user = TenantUser::where('email', $adminData['admin_email'])->first();
            
            if (!$user) {
                // Créer le nouvel utilisateur dans la base du tenant
                $user = TenantUser::create([
                    'name' => $adminData['admin_first_name'] . ' ' . $adminData['admin_last_name'],
                    'email' => $adminData['admin_email'],
                    'password' => Hash::make($adminData['admin_password']),
                    'role' => 'admin',
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
