<?php

namespace Database\Factories;

use App\Models\AppDatabase;
use App\Models\Application;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AppDatabase>
 */
class AppDatabaseFactory extends Factory
{
    protected $model = AppDatabase::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $dbName = 'app_' . Str::random(16) . '_db';
        
        return [
            'application_id' => Application::factory(),
            'database_name' => $dbName,
            'db_username' => 'db_' . Str::random(12),
            'db_password' => bcrypt('password_' . Str::random(16)),
            'db_host' => 'localhost',
            'db_port' => 3306,
            'status' => 'active',
        ];
    }

    /**
     * Indicate that the database is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }
}
