<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Vérifier si un super-admin existe déjà
        $existingAdmin = AdminUser::where('email', 'admin@medkey.com')->first();
        
        if (!$existingAdmin) {
            AdminUser::create([
                'name' => 'Super Administrateur',
                'email' => 'admin@medkey.com',
                'password' => Hash::make('admin123'), // Mot de passe par défaut - À CHANGER EN PRODUCTION
                'role' => 'super_admin',
                'status' => 'active',
                'email_verified_at' => now(),
            ]);
            
            $this->command->info('Super-admin créé avec succès !');
            $this->command->info('Email: admin@medkey.com');
            $this->command->info('Mot de passe: admin123');
            $this->command->warn('⚠️  IMPORTANT: Changez le mot de passe après la première connexion !');
        } else {
            $this->command->info('Un super-admin existe déjà avec l\'email admin@medkey.com');
        }
    }
}
