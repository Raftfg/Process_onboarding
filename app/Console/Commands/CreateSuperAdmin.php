<?php

namespace App\Console\Commands;

use App\Models\AdminUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateSuperAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:create-super-admin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Créer un super-admin pour gérer tous les tenants';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Création d\'un super-admin...');

        $name = $this->ask('Nom complet');
        $email = $this->ask('Email');
        $password = $this->secret('Mot de passe (min 8 caractères)');
        $passwordConfirmation = $this->secret('Confirmer le mot de passe');

        // Validation
        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:admin_users,email',
            'password' => 'required|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            $this->error('Erreurs de validation:');
            foreach ($validator->errors()->all() as $error) {
                $this->error('  - ' . $error);
            }
            return 1;
        }

        // Créer le super-admin
        $admin = AdminUser::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'super_admin',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->info("Super-admin créé avec succès!");
        $this->info("Email: {$admin->email}");
        $this->info("Vous pouvez maintenant vous connecter à /admin/login");

        return 0;
    }
}
