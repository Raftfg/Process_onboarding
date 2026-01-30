<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\OnboardingService;
use App\Services\TenantService;
use App\Models\OnboardingSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;

class TestOnboarding extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:onboarding 
                            {--clean : Supprimer les données de test après les tests}
                            {--subdomain= : Utiliser un sous-domaine spécifique}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Teste le processus d\'onboarding complet';

    protected $onboardingService;
    protected $tenantService;
    protected $testSubdomain;
    protected $testDatabase;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->onboardingService = app(OnboardingService::class);
        $this->tenantService = app(TenantService::class);

        $this->info('🧪 Démarrage des tests d\'onboarding...');
        $this->newLine();

        // Générer un sous-domaine de test initial (sera mis à jour après l'onboarding)
        $initialSubdomain = $this->option('subdomain') ?? 'test-' . time();
        $this->testSubdomain = $initialSubdomain;
        $this->testDatabase = 'medkey_' . $this->testSubdomain;

        $this->info("📋 Sous-domaine initial: {$this->testSubdomain}");
        $this->info("ℹ️  Note: Le sous-domaine sera généré automatiquement lors de l'onboarding");
        $this->newLine();

        $tests = [
            'testDatabaseCreation' => 'Création de la base de données',
            'testOnboardingProcess' => 'Processus d\'onboarding complet',
            'testUserCreation' => 'Création de l\'utilisateur admin',
            'testDatabaseSwitch' => 'Basculement vers la base tenant',
            'testUserAuthentication' => 'Authentification de l\'utilisateur',
            'testOnboardingSession' => 'Session d\'onboarding',
        ];

        $results = [];
        foreach ($tests as $method => $description) {
            $this->info("▶️  {$description}...");
            try {
                $result = $this->$method();
                $results[$method] = ['status' => 'success', 'message' => $result];
                $this->info("   ✅ {$description}: {$result}");
            } catch (\Exception $e) {
                $results[$method] = ['status' => 'error', 'message' => $e->getMessage()];
                $this->error("   ❌ {$description}: {$e->getMessage()}");
            }
            $this->newLine();
        }

        // Afficher le résumé
        $this->displaySummary($results);

        // Nettoyage si demandé
        if ($this->option('clean')) {
            $this->cleanup();
        } else {
            $this->warn("💡 Utilisez --clean pour supprimer les données de test");
        }

        return Command::SUCCESS;
    }

    protected function testDatabaseCreation()
    {
        // Vérifier si la base existe
        $exists = $this->databaseExists($this->testDatabase);
        if ($exists) {
            return "Base de données existe déjà";
        }

        // Créer la base de données
        $rootUsername = config('database.connections.mysql.root_username', env('DB_ROOT_USERNAME', 'root'));
        $rootPassword = config('database.connections.mysql.root_password', env('DB_ROOT_PASSWORD', ''));

        $pdo = new \PDO(
            "mysql:host=" . config('database.connections.mysql.host'),
            $rootUsername,
            $rootPassword
        );

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$this->testDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        if ($this->databaseExists($this->testDatabase)) {
            return "Base de données créée avec succès";
        }

        throw new \Exception("Échec de la création de la base de données");
    }

    protected function testOnboardingProcess()
    {
        $testData = [
            'step1' => [
                'hospital_name' => 'Hôpital de Test',
                'hospital_address' => '123 Rue de Test',
                'hospital_phone' => '+33 1 23 45 67 89',
                'hospital_email' => 'test@hospital.com',
            ],
            'step2' => [
                'admin_first_name' => 'Test',
                'admin_last_name' => 'Admin',
                'admin_email' => 'admin@test.com',
                'admin_password' => 'TestPassword123!',
            ],
        ];

        $result = $this->onboardingService->processOnboarding($testData);

        if (isset($result['subdomain']) && isset($result['database'])) {
            // Mettre à jour le sous-domaine et la base de données avec ceux générés
            $this->testSubdomain = $result['subdomain'];
            $this->testDatabase = $result['database'];
            
            $this->info("   ℹ️  Sous-domaine généré: {$this->testSubdomain}");
            $this->info("   ℹ️  Base de données générée: {$this->testDatabase}");
            
            return "Onboarding complété - Subdomain: {$result['subdomain']}, Database: {$result['database']}";
        }

        throw new \Exception("Échec du processus d'onboarding");
    }

    protected function testUserCreation()
    {
        // Utiliser la base de données générée lors de l'onboarding
        if (!$this->testDatabase) {
            throw new \Exception("Base de données non définie. L'onboarding doit être exécuté en premier.");
        }

        // Basculer vers la base du tenant
        $this->tenantService->switchToTenantDatabase($this->testDatabase);

        // Vérifier si l'utilisateur existe
        $user = User::where('email', 'admin@test.com')->first();

        if ($user) {
            // Revenir à la base principale
            Config::set('database.default', 'mysql');
            DB::purge('tenant');
            
            return "Utilisateur trouvé: {$user->email} (ID: {$user->id})";
        }

        // Revenir à la base principale avant de lancer l'exception
        Config::set('database.default', 'mysql');
        DB::purge('tenant');
        
        throw new \Exception("Utilisateur non trouvé dans la base tenant");
    }

    protected function testDatabaseSwitch()
    {
        // Utiliser la base de données générée lors de l'onboarding
        if (!$this->testDatabase) {
            throw new \Exception("Base de données non définie. L'onboarding doit être exécuté en premier.");
        }

        // Revenir à la base principale
        Config::set('database.default', 'mysql');
        DB::purge('tenant');

        $mainDb = DB::connection()->getDatabaseName();
        if ($mainDb !== config('database.connections.mysql.database')) {
            throw new \Exception("Échec du retour à la base principale");
        }

        // Basculer vers la base tenant
        $this->tenantService->switchToTenantDatabase($this->testDatabase);

        $tenantDb = DB::connection()->getDatabaseName();
        if ($tenantDb !== $this->testDatabase) {
            // Revenir à la base principale avant de lancer l'exception
            Config::set('database.default', 'mysql');
            DB::purge('tenant');
            throw new \Exception("Échec du basculement vers la base tenant. Attendu: {$this->testDatabase}, Obtenu: {$tenantDb}");
        }

        // Revenir à la base principale
        Config::set('database.default', 'mysql');
        DB::purge('tenant');

        return "Basculement réussi: {$mainDb} → {$tenantDb} → {$mainDb}";
    }

    protected function testUserAuthentication()
    {
        // Utiliser la base de données générée lors de l'onboarding
        if (!$this->testDatabase) {
            throw new \Exception("Base de données non définie. L'onboarding doit être exécuté en premier.");
        }

        // Basculer vers la base du tenant
        $this->tenantService->switchToTenantDatabase($this->testDatabase);

        // Tester l'authentification
        $user = User::where('email', 'admin@test.com')->first();
        if (!$user) {
            // Revenir à la base principale avant de lancer l'exception
            Config::set('database.default', 'mysql');
            DB::purge('tenant');
            throw new \Exception("Utilisateur non trouvé");
        }

        // Vérifier le mot de passe
        if (!Hash::check('TestPassword123!', $user->password)) {
            // Revenir à la base principale avant de lancer l'exception
            Config::set('database.default', 'mysql');
            DB::purge('tenant');
            throw new \Exception("Mot de passe incorrect");
        }

        // Revenir à la base principale
        Config::set('database.default', 'mysql');
        DB::purge('tenant');

        return "Authentification réussie pour: {$user->email}";
    }

    protected function testOnboardingSession()
    {
        // Revenir à la base principale
        Config::set('database.default', 'mysql');
        DB::purge('tenant');

        $session = OnboardingSession::where('subdomain', $this->testSubdomain)
            ->where('status', 'completed')
            ->first();

        if (!$session) {
            throw new \Exception("Session d'onboarding non trouvée");
        }

        return "Session trouvée - Hospital: {$session->hospital_name}, Admin: {$session->admin_email}";
    }

    protected function databaseExists(string $databaseName): bool
    {
        try {
            $rootUsername = config('database.connections.mysql.root_username', env('DB_ROOT_USERNAME', 'root'));
            $rootPassword = config('database.connections.mysql.root_password', env('DB_ROOT_PASSWORD', ''));

            $pdo = new \PDO(
                "mysql:host=" . config('database.connections.mysql.host'),
                $rootUsername,
                $rootPassword
            );

            $stmt = $pdo->query("SHOW DATABASES LIKE '{$databaseName}'");
            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function displaySummary(array $results)
    {
        $this->newLine();
        $this->info('📊 Résumé des tests:');
        $this->newLine();

        $successCount = 0;
        $errorCount = 0;

        foreach ($results as $test => $result) {
            $status = $result['status'] === 'success' ? '✅' : '❌';
            $this->line("  {$status} " . str_replace('test', '', $test));
            if ($result['status'] === 'success') {
                $successCount++;
            } else {
                $errorCount++;
            }
        }

        $this->newLine();
        $this->info("✅ Succès: {$successCount}");
        if ($errorCount > 0) {
            $this->error("❌ Erreurs: {$errorCount}");
        }
    }

    protected function cleanup()
    {
        $this->info('🧹 Nettoyage des données de test...');

        try {
            // Supprimer la session d'onboarding
            Config::set('database.default', 'mysql');
            DB::purge('tenant');

            OnboardingSession::where('subdomain', $this->testSubdomain)->delete();
            $this->info("   ✅ Session d'onboarding supprimée");

            // Supprimer la base de données
            $rootUsername = config('database.connections.mysql.root_username', env('DB_ROOT_USERNAME', 'root'));
            $rootPassword = config('database.connections.mysql.root_password', env('DB_ROOT_PASSWORD', ''));

            $pdo = new \PDO(
                "mysql:host=" . config('database.connections.mysql.host'),
                $rootUsername,
                $rootPassword
            );

            $pdo->exec("DROP DATABASE IF EXISTS `{$this->testDatabase}`");
            $this->info("   ✅ Base de données supprimée");

            $this->info('✅ Nettoyage terminé');
        } catch (\Exception $e) {
            $this->error("❌ Erreur lors du nettoyage: {$e->getMessage()}");
        }
    }
}
