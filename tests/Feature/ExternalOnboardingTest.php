<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\OnboardingService;
use App\Models\OnboardingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class ExternalOnboardingTest extends TestCase
{
    protected $onboardingService;
    protected $testSubdomain;
    protected $testDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->onboardingService = app(OnboardingService::class);
        $this->testSubdomain = 'ext-test-' . time();
        $this->testDatabase = 'akasigroup_' . $this->testSubdomain;
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    /** @test */
    public function it_can_process_external_onboarding_with_dynamic_migrations_and_callback()
    {
        // Fake HTTP for callback verification
        Http::fake();

        // 1. Prepare dynamic migration content
        $migrationContent = '<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create("sector_specific_table", function (Blueprint $table) {
            $table->id();
            $table->string("custom_field");
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists("sector_specific_table");
    }
};';

        $payload = [
            'email' => 'external@admin.com',
            'organization_name' => 'External Sector Clinic',
            'callback_url' => 'https://external-app.com/api/callback',
            'migrations' => [
                [
                    'filename' => '2024_01_01_000000_create_sector_table.php',
                    'content' => $migrationContent
                ]
            ]
        ];

        // 2. Call the API
        $response = $this->postJson('/api/v1/onboarding/external', $payload);

        // 3. Asset success response
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        
        $result = $response->json('result');
        $this->assertNotNull($result['subdomain']);
        $this->assertEquals('external@admin.com', $result['email']);

        // 4. Verify Database and Table Creation
        $this->assertTrue($this->databaseExists($result['database']));
        
        // Switch to tenant DB to verify table
        Config::set('database.connections.tenant.database', $result['database']);
        DB::purge('tenant');
        $this->assertTrue(Schema::connection('tenant')->hasTable('sector_specific_table'), 'Custom table should exist in tenant DB');

        // 5. Verify Callback was sent
        Http::assertSent(function ($request) use ($payload) {
            return $request->url() == $payload['callback_url'] &&
                   $request['organization_name'] == $payload['organization_name'] &&
                   !empty($request['subdomain']); // Check that relevant data is present
        });
    }

    protected function databaseExists(string $databaseName): bool
    {
        try {
            $pdo = DB::connection('mysql')->getPdo();
            $stmt = $pdo->query("SHOW DATABASES LIKE '{$databaseName}'");
            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function cleanup()
    {
        try {
            Config::set('database.default', 'mysql');
            DB::purge('tenant');
            OnboardingSession::where('subdomain', $this->testSubdomain)->delete();

            // Find any DBs created during test
            $pdo = DB::connection('mysql')->getPdo();
            $stmt = $pdo->query("SHOW DATABASES LIKE 'akasigroup_%'");
            $dbs = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            
            foreach ($dbs as $db) {
                if (str_contains($db, 'ext-test')) {
                    $pdo->exec("DROP DATABASE IF EXISTS `{$db}`");
                }
            }
        } catch (\Exception $e) {
            // Ignore cleanup errors
        }
    }
}
