<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\Application;
use App\Models\OnboardingRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OnboardingIdempotenceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_provision_is_idempotent()
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

        // Première tentative de provisioning
        $response1 = $this->postJson('/api/v1/onboarding/provision', [
            'uuid' => $uuid,
            'generate_api_key' => true,
        ], [
            'X-Master-Key' => $masterKey,
        ]);

        $this->assertEquals(200, $response1->status());
        $apiKey1 = $response1->json()['api_key'];
        $this->assertNotNull($apiKey1);

        // Deuxième tentative (idempotente) - devrait retourner les mêmes données
        // Note: En production, cela sera bloqué par le rate limit, mais on peut tester la logique
        $registration = OnboardingRegistration::where('uuid', $uuid)->first();
        $registration->update(['status' => 'activated', 'dns_configured' => true, 'ssl_configured' => true]);

        // Simuler un appel idempotent (bypass rate limit pour le test)
        $response2 = $this->postJson('/api/v1/onboarding/provision', [
            'uuid' => $uuid,
        ], [
            'X-Master-Key' => $masterKey,
        ]);

        $this->assertEquals(200, $response2->status());
        $this->assertTrue($response2->json()['metadata']['is_idempotent'] ?? false);
        $this->assertNull($response2->json()['api_key']); // Pas de nouvelle clé
    }
}
