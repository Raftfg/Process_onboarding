<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\Application;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OnboardingRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('onboarding:start:app:*');
        RateLimiter::clear('onboarding:provision:uuid:*');
        RateLimiter::clear('onboarding:status:app:*');
        RateLimiter::clear('onboarding:ip:*');
    }

    /** @test */
    public function test_start_endpoint_rate_limit()
    {
        // Créer une application avec base de données
        $application = Application::factory()->create();
        $appDatabase = \App\Models\AppDatabase::factory()->create([
            'application_id' => $application->id,
            'status' => 'active',
        ]);
        $application->refresh(); // Recharger pour avoir la relation
        
        $masterKey = 'mk_test_' . str()->random(48);

        // Créer une master key hashée
        $application->update(['master_key' => bcrypt($masterKey)]);

        // Faire 10 requêtes (limite)
        for ($i = 0; $i < 10; $i++) {
            $response = $this->postJson('/api/v1/onboarding/start', [
                'email' => "test{$i}@example.com",
                'organization_name' => "Test Org {$i}",
            ], [
                'X-Master-Key' => $masterKey,
            ]);

            $this->assertContains($response->status(), [201, 422]);
        }

        // La 11ème devrait être bloquée
        $response = $this->postJson('/api/v1/onboarding/start', [
            'email' => 'test11@example.com',
            'organization_name' => 'Test Org 11',
        ], [
            'X-Master-Key' => $masterKey,
        ]);

        $this->assertEquals(429, $response->status());
        $this->assertArrayHasKey('retry_after_minutes', $response->json());
    }

    /** @test */
    public function test_provision_endpoint_rate_limit()
    {
        // Créer une application avec base de données
        $application = Application::factory()->create();
        $appDatabase = \App\Models\AppDatabase::factory()->create([
            'application_id' => $application->id,
            'status' => 'active',
        ]);
        $application->refresh();
        
        $masterKey = 'mk_test_' . str()->random(48);
        $application->update(['master_key' => bcrypt($masterKey)]);

        // Créer un onboarding
        $startResponse = $this->postJson('/api/v1/onboarding/start', [
            'email' => 'test@example.com',
            'organization_name' => 'Test Org',
        ], [
            'X-Master-Key' => $masterKey,
        ]);

        $uuid = $startResponse->json()['uuid'];

        // Première tentative de provisioning (devrait réussir)
        $response = $this->postJson('/api/v1/onboarding/provision', [
            'uuid' => $uuid,
        ], [
            'X-Master-Key' => $masterKey,
        ]);

        $this->assertEquals(200, $response->status());

        // Deuxième tentative (devrait être bloquée - 1/24h)
        $response = $this->postJson('/api/v1/onboarding/provision', [
            'uuid' => $uuid,
        ], [
            'X-Master-Key' => $masterKey,
        ]);

        $this->assertEquals(429, $response->status());
    }

    /** @test */
    public function test_status_endpoint_rate_limit()
    {
        // Créer une application avec base de données
        $application = Application::factory()->create();
        $appDatabase = \App\Models\AppDatabase::factory()->create([
            'application_id' => $application->id,
            'status' => 'active',
        ]);
        $application->refresh();
        
        $masterKey = 'mk_test_' . str()->random(48);
        $application->update(['master_key' => bcrypt($masterKey)]);

        // Créer un onboarding
        $startResponse = $this->postJson('/api/v1/onboarding/start', [
            'email' => 'test@example.com',
            'organization_name' => 'Test Org',
        ], [
            'X-Master-Key' => $masterKey,
        ]);

        $uuid = $startResponse->json()['uuid'];

        // Faire 100 requêtes (limite)
        for ($i = 0; $i < 100; $i++) {
            $response = $this->getJson("/api/v1/onboarding/status/{$uuid}", [
                'X-Master-Key' => $masterKey,
            ]);

            $this->assertEquals(200, $response->status());
        }

        // La 101ème devrait être bloquée
        $response = $this->getJson("/api/v1/onboarding/status/{$uuid}", [
            'X-Master-Key' => $masterKey,
        ]);

        $this->assertEquals(429, $response->status());
    }

    /** @test */
    public function test_rate_limit_headers()
    {
        // Créer une application avec base de données
        $application = Application::factory()->create();
        $appDatabase = \App\Models\AppDatabase::factory()->create([
            'application_id' => $application->id,
            'status' => 'active',
        ]);
        $application->refresh();
        
        $masterKey = 'mk_test_' . str()->random(48);
        $application->update(['master_key' => bcrypt($masterKey)]);

        $response = $this->postJson('/api/v1/onboarding/start', [
            'email' => 'test@example.com',
            'organization_name' => 'Test Org',
        ], [
            'X-Master-Key' => $masterKey,
        ]);

        $this->assertTrue($response->headers->has('X-RateLimit-Limit'));
        $this->assertTrue($response->headers->has('X-RateLimit-Remaining'));
        $this->assertTrue($response->headers->has('X-RateLimit-Reset'));
    }
}
