<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OnboardingEnrichedResponseTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_start_response_contains_metadata()
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

        $this->assertEquals(201, $response->status());
        $data = $response->json();

        // Vérifier les champs de base
        $this->assertArrayHasKey('uuid', $data);
        $this->assertArrayHasKey('subdomain', $data);
        $this->assertArrayHasKey('email', $data);
        $this->assertArrayHasKey('onboarding_status', $data);

        // Vérifier les metadata enrichies
        $this->assertArrayHasKey('metadata', $data);
        $metadata = $data['metadata'];

        $this->assertArrayHasKey('created_at', $metadata);
        $this->assertArrayHasKey('updated_at', $metadata);
        $this->assertArrayHasKey('dns_configured', $metadata);
        $this->assertArrayHasKey('ssl_configured', $metadata);
        $this->assertArrayHasKey('infrastructure_status', $metadata);
        $this->assertArrayHasKey('api_key_generated', $metadata);
        $this->assertArrayHasKey('provisioning_attempts', $metadata);

        $this->assertEquals('pending', $metadata['infrastructure_status']);
        $this->assertFalse($metadata['api_key_generated']);
        $this->assertEquals(0, $metadata['provisioning_attempts']);
    }

    /** @test */
    public function test_provision_response_contains_metadata()
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

        // Provisionner
        $response = $this->postJson('/api/v1/onboarding/provision', [
            'uuid' => $uuid,
            'generate_api_key' => true,
        ], [
            'X-Master-Key' => $masterKey,
        ]);

        $this->assertEquals(200, $response->status());
        $data = $response->json();

        // Vérifier les metadata
        $this->assertArrayHasKey('metadata', $data);
        $metadata = $data['metadata'];

        $this->assertArrayHasKey('infrastructure_status', $metadata);
        $this->assertArrayHasKey('provisioning_attempts', $metadata);
        $this->assertGreaterThan(0, $metadata['provisioning_attempts']);
    }
}
