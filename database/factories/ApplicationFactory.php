<?php

namespace Database\Factories;

use App\Models\Application;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Application>
 */
class ApplicationFactory extends Factory
{
    protected $model = Application::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $appName = $this->faker->unique()->slug();
        
        return [
            'app_id' => 'app_' . Str::random(32),
            'app_name' => $appName,
            'display_name' => $this->faker->company(),
            'contact_email' => $this->faker->safeEmail(),
            'website' => $this->faker->optional()->url(),
            'master_key' => Hash::make('mk_test_' . Str::random(48)),
            'is_active' => true,
            'last_used_at' => $this->faker->optional()->dateTime(),
        ];
    }
}
