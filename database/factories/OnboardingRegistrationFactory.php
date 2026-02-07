<?php

namespace Database\Factories;

use App\Models\OnboardingRegistration;
use App\Models\Application;
use App\Models\AppDatabase;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OnboardingRegistration>
 */
class OnboardingRegistrationFactory extends Factory
{
    protected $model = OnboardingRegistration::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subdomain = $this->faker->unique()->slug();
        
        return [
            'uuid' => (string) Str::uuid(),
            'application_id' => Application::factory(),
            'app_database_id' => AppDatabase::factory(),
            'email' => $this->faker->safeEmail(),
            'organization_name' => $this->faker->company(),
            'subdomain' => $subdomain,
            'status' => $this->faker->randomElement(['pending', 'activated', 'cancelled']),
            'api_key' => null,
            'api_secret' => null,
            'metadata' => [],
            'dns_configured' => false,
            'ssl_configured' => false,
            'provisioning_attempts' => 0,
        ];
    }

    /**
     * Indicate that the onboarding is activated.
     */
    public function activated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'activated',
            'dns_configured' => true,
            'ssl_configured' => true,
        ]);
    }

    /**
     * Indicate that the onboarding is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'dns_configured' => false,
            'ssl_configured' => false,
        ]);
    }
}
