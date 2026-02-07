<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\SubdomainService;
use App\Models\OnboardingRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SubdomainValidationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_subdomain_validation_format()
    {
        $service = app(SubdomainService::class);

        // Sous-domaines valides
        $this->assertTrue($service->isValidSubdomain('test-org'));
        $this->assertTrue($service->isValidSubdomain('test123'));
        $this->assertTrue($service->isValidSubdomain('a'));

        // Sous-domaines invalides
        $this->assertFalse($service->isValidSubdomain('test_org')); // underscore
        $this->assertFalse($service->isValidSubdomain('Test-Org')); // majuscules
        $this->assertFalse($service->isValidSubdomain('-test')); // commence par tiret
        $this->assertFalse($service->isValidSubdomain('test-')); // finit par tiret
        $this->assertFalse($service->isValidSubdomain('')); // vide
    }

    /** @test */
    public function test_subdomain_uniqueness_check()
    {
        $service = app(SubdomainService::class);

        // Créer un onboarding avec un sous-domaine
        OnboardingRegistration::factory()->create([
            'subdomain' => 'existing-subdomain',
        ]);

        // Vérifier que le sous-domaine existe
        $check = $service->checkSubdomainAvailability('existing-subdomain');
        $this->assertFalse($check['available']);
        $this->assertTrue($check['exists_in_db']);

        // Vérifier qu'un nouveau sous-domaine est disponible
        $check = $service->checkSubdomainAvailability('new-subdomain');
        $this->assertTrue($check['available']);
        $this->assertFalse($check['exists_in_db']);
    }

    /** @test */
    public function test_subdomain_generation_with_retry()
    {
        $service = app(SubdomainService::class);

        // Créer plusieurs sous-domaines pour forcer un retry
        OnboardingRegistration::factory()->create(['subdomain' => 'test-org']);
        OnboardingRegistration::factory()->create(['subdomain' => 'test-org-1']);
        OnboardingRegistration::factory()->create(['subdomain' => 'test-org-2']);

        // Générer un nouveau sous-domaine (devrait utiliser test-org-3)
        $subdomain = $service->generateUniqueSubdomain('Test Org', 'test@example.com');

        $this->assertNotEquals('test-org', $subdomain);
        $this->assertNotEquals('test-org-1', $subdomain);
        $this->assertNotEquals('test-org-2', $subdomain);
        $this->assertTrue($service->isValidSubdomain($subdomain));
    }
}
