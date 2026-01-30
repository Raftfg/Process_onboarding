<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\TenantService;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class TestTenantConnection extends Command
{
    protected $signature = 'tenant:test {subdomain} {email?}';
    protected $description = 'Teste la connexion à la base de données d\'un tenant';

    protected $tenantService;

    public function __construct(TenantService $tenantService)
    {
        parent::__construct();
        $this->tenantService = $tenantService;
    }

    public function handle()
    {
        $subdomain = $this->argument('subdomain');
        $email = $this->argument('email');

        $this->info("Test de connexion pour le tenant: {$subdomain}");

        // Vérifier que le tenant existe
        if (!$this->tenantService->tenantExists($subdomain)) {
            $this->error("Le tenant '{$subdomain}' n'existe pas ou n'est pas actif.");
            return 1;
        }

        // Récupérer la base de données
        $databaseName = $this->tenantService->getTenantDatabase($subdomain);
        if (!$databaseName) {
            $this->error("Impossible de trouver la base de données pour le tenant '{$subdomain}'.");
            return 1;
        }

        $this->info("Base de données: {$databaseName}");

        // Basculer vers la base du tenant
        try {
            $this->tenantService->switchToTenantDatabase($databaseName);
            $this->info("✓ Connexion à la base de données réussie");

            // Vérifier la connexion
            $currentDatabase = DB::connection()->getDatabaseName();
            $this->info("Base de données actuelle: {$currentDatabase}");

            // Compter les utilisateurs
            $userCount = User::count();
            $this->info("Nombre d'utilisateurs: {$userCount}");

            // Si un email est fourni, chercher l'utilisateur
            if ($email) {
                $user = User::where('email', $email)->first();
                if ($user) {
                    $this->info("✓ Utilisateur trouvé:");
                    $this->line("  - ID: {$user->id}");
                    $this->line("  - Nom: {$user->name}");
                    $this->line("  - Email: {$user->email}");
                    $this->line("  - Rôle: {$user->role}");
                } else {
                    $this->warn("✗ Utilisateur '{$email}' non trouvé dans la base de données.");
                }
            } else {
                // Lister les utilisateurs
                $users = User::limit(10)->get();
                if ($users->count() > 0) {
                    $this->info("Utilisateurs trouvés:");
                    foreach ($users as $user) {
                        $this->line("  - {$user->email} ({$user->name}) - {$user->role}");
                    }
                } else {
                    $this->warn("Aucun utilisateur trouvé dans la base de données.");
                }
            }

            // Revenir à la base principale
            Config::set('database.default', 'mysql');
            DB::purge('tenant');

            $this->info("✓ Test terminé avec succès");
            return 0;
        } catch (\Exception $e) {
            $this->error("Erreur: " . $e->getMessage());
            $this->error("Trace: " . $e->getTraceAsString());
            
            // Revenir à la base principale en cas d'erreur
            Config::set('database.default', 'mysql');
            DB::purge('tenant');
            
            return 1;
        }
    }
}
