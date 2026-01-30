<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\OnboardingService;
use App\Services\TenantService;
use App\Models\OnboardingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;

class OnboardingTest extends TestCase
{
    protected $onboardingService;
    protected $tenantService;
    protected $testSubdomain;
    protected $testDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->onboardingService = app(OnboardingService::class);
        $this->tenantService = app(TenantService::class);
        $this->testSubdomain = 'test-' . time();
        $this->testDatabase = 'medkey_' . $this->testSubdomain;
    }

    protected function tearDown(): void
    {
        // Nettoyer après chaque test
        $this->cleanup();
        parent::tearDown();
    }

    /** @test */
    public function it_can_create_a_database_for_tenant()
    {
        $this->assertFalse($this->databaseExists($this->testDatabase));

        $testData = [
            'step1' => [
                'hospital_name' => 'Test Hospital',
                'hospital_address' => '123 Test St',
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

        $this->assertTrue($this->databaseExists($result['database']));
    }

    /** @test */
    public function it_can_create_admin_user_in_tenant_database()
    {
        $testData = [
            'step1' => [
                'hospital_name' => 'Test Hospital',
            ],
            'step2' => [
                'admin_first_name' => 'Test',
                'admin_last_name' => 'Admin',
                'admin_email' => 'admin@test.com',
                'admin_password' => 'TestPassword123!',
            ],
        ];

        $result = $this->onboardingService->processOnboarding($testData);

        // Basculer vers la base tenant
        $this->tenantService->switchToTenantDatabase($result['database']);

        $user = User::where('email', 'admin@test.com')->first();

        $this->assertNotNull($user);
        $this->assertEquals('Test Admin', $user->name);
        $this->assertTrue(Hash::check('TestPassword123!', $user->password));
    }

    /** @test */
    public function it_can_switch_between_databases()
    {
        $testData = [
            'step1' => ['hospital_name' => 'Test Hospital'],
            'step2' => [
                'admin_first_name' => 'Test',
                'admin_last_name' => 'Admin',
                'admin_email' => 'admin@test.com',
                'admin_password' => 'TestPassword123!',
            ],
        ];

        $result = $this->onboardingService->processOnboarding($testData);

        // Vérifier qu'on est sur la base principale
        Config::set('database.default', 'mysql');
        DB::purge('tenant');
        $mainDb = DB::connection()->getDatabaseName();

        // Basculer vers tenant
        $this->tenantService->switchToTenantDatabase($result['database']);
        $tenantDb = DB::connection()->getDatabaseName();

        $this->assertNotEquals($mainDb, $tenantDb);
        $this->assertEquals($result['database'], $tenantDb);
    }

    /** @test */
    public function it_creates_onboarding_session()
    {
        $testData = [
            'step1' => [
                'hospital_name' => 'Test Hospital',
                'hospital_address' => '123 Test St',
            ],
            'step2' => [
                'admin_first_name' => 'Test',
                'admin_last_name' => 'Admin',
                'admin_email' => 'admin@test.com',
                'admin_password' => 'TestPassword123!',
            ],
        ];

        $result = $this->onboardingService->processOnboarding($testData);

        // Revenir à la base principale
        Config::set('database.default', 'mysql');
        DB::purge('tenant');

        $session = OnboardingSession::where('subdomain', $result['subdomain'])
            ->where('status', 'completed')
            ->first();

        $this->assertNotNull($session);
        $this->assertEquals('Test Hospital', $session->hospital_name);
        $this->assertEquals('admin@test.com', $session->admin_email);
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

    protected function cleanup()
    {
        try {
            // Supprimer la session
            Config::set('database.default', 'mysql');
            DB::purge('tenant');
            OnboardingSession::where('subdomain', $this->testSubdomain)->delete();

            // Supprimer la base de données
            if ($this->databaseExists($this->testDatabase)) {
                $rootUsername = config('database.connections.mysql.root_username', env('DB_ROOT_USERNAME', 'root'));
                $rootPassword = config('database.connections.mysql.root_password', env('DB_ROOT_PASSWORD', ''));

                $pdo = new \PDO(
                    "mysql:host=" . config('database.connections.mysql.host'),
                    $rootUsername,
                    $rootPassword
                );

                $pdo->exec("DROP DATABASE IF EXISTS `{$this->testDatabase}`");
            }
        } catch (\Exception $e) {
            // Ignorer les erreurs de nettoyage
        }
    }
}
