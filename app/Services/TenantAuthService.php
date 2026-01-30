<?php

namespace App\Services;

use App\Models\Tenant\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;

class TenantAuthService
{
    protected $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    /**
     * Authentifie un utilisateur dans la base de données du tenant
     *
     * @param string $email
     * @param string $password
     * @param string|null $subdomain Si null, utilise le tenant actuel depuis la session
     * @param bool $remember
     * @return User|null
     * @throws ValidationException
     */
    public function authenticate(string $email, string $password, ?string $subdomain = null, bool $remember = false): ?User
    {
        // Si pas de sous-domaine fourni, essayer de le récupérer depuis la session
        if (!$subdomain) {
            $subdomain = session('current_subdomain');
        }

        if (!$subdomain) {
            throw ValidationException::withMessages([
                'email' => ['Impossible de déterminer le tenant.'],
            ]);
        }

        // Vérifier que le tenant existe et est actif
        if (!$this->tenantService->tenantExists($subdomain)) {
            throw ValidationException::withMessages([
                'email' => ['Le tenant spécifié n\'existe pas ou n\'est pas actif.'],
            ]);
        }

        // Récupérer la base de données du tenant
        $databaseName = $this->tenantService->getTenantDatabase($subdomain);
        
        if (!$databaseName) {
            Log::error("Base de données non trouvée pour le tenant", ['subdomain' => $subdomain]);
            throw ValidationException::withMessages([
                'email' => ['Impossible de trouver la base de données du tenant.'],
            ]);
        }

        // Vérifier si on est déjà sur la bonne base
        $currentDatabase = DB::connection()->getDatabaseName();
        $currentConnection = DB::connection()->getName();
        
        // Basculer vers la base du tenant seulement si nécessaire
        if ($currentDatabase !== $databaseName || $currentConnection !== 'tenant') {
            Log::info("Basculement vers la base du tenant", [
                'from_database' => $currentDatabase,
                'to_database' => $databaseName,
                'from_connection' => $currentConnection,
            ]);
            $this->tenantService->switchToTenantDatabase($databaseName);
        }

        // S'assurer que le modèle User utilise la connexion tenant
        // Le modèle User a $connection = null, donc il utilisera la connexion par défaut (tenant)
        
        // Configurer le modèle d'authentification pour utiliser Tenant\User
        Config::set('auth.providers.users.model', \App\Models\Tenant\User::class);
        
        try {
            // Vérifier que la connexion est bien configurée après le basculement
            $currentConnection = DB::connection()->getName();
            $currentDatabase = DB::connection()->getDatabaseName();
            
            Log::info("Tentative d'authentification", [
                'email' => $email,
                'subdomain' => $subdomain,
                'database_name' => $databaseName,
                'current_connection' => $currentConnection,
                'current_database' => $currentDatabase,
            ]);

            // Vérifier que la base de données existe et est accessible
            if ($currentDatabase !== $databaseName) {
                Log::error("La base de données ne correspond pas", [
                    'expected' => $databaseName,
                    'actual' => $currentDatabase,
                ]);
                throw new \Exception("La connexion à la base de données du tenant a échoué.");
            }

            // Rechercher l'utilisateur dans la base du tenant
            // Le modèle User utilise la connexion par défaut (tenant) après le switch
            $user = User::where('email', $email)->first();
            
            Log::info("Recherche utilisateur", [
                'email' => $email,
                'user_found' => $user !== null,
                'user_id' => $user ? $user->id : null,
            ]);

            if (!$user) {
                Log::warning("Utilisateur non trouvé", ['email' => $email, 'database' => $databaseName]);
                throw ValidationException::withMessages([
                    'email' => ['Les identifiants fournis ne correspondent pas à nos enregistrements.'],
                ]);
            }

            // Vérifier le mot de passe
            if (!Hash::check($password, $user->password)) {
                Log::warning("Mot de passe incorrect", ['email' => $email, 'user_id' => $user->id]);
                throw ValidationException::withMessages([
                    'email' => ['Les identifiants fournis ne correspondent pas à nos enregistrements.'],
                ]);
            }

            // Connecter l'utilisateur
            // S'assurer que le guard utilise le bon provider
            Auth::guard('web')->login($user, $remember);

            // Stocker le sous-domaine dans la session
            session(['current_subdomain' => $subdomain]);

            Log::info("Utilisateur authentifié avec succès", [
                'email' => $email,
                'tenant' => $subdomain,
                'user_id' => $user->id,
            ]);

            return $user;
        } catch (ValidationException $e) {
            // Revenir à la base principale en cas d'erreur
            Config::set('database.default', 'mysql');
            DB::purge('tenant');
            throw $e;
        } catch (\Exception $e) {
            Log::error("Erreur lors de l'authentification", [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'email' => $email,
                'subdomain' => $subdomain,
            ]);
            
            // Revenir à la base principale en cas d'erreur
            Config::set('database.default', 'mysql');
            DB::purge('tenant');
            
            throw ValidationException::withMessages([
                'email' => ['Une erreur est survenue lors de l\'authentification : ' . $e->getMessage()],
            ]);
        }
    }

    /**
     * Récupère le tenant actuel depuis la session ou la requête
     *
     * @return \App\Models\Tenant|null
     */
    public function getCurrentTenant(): ?\App\Models\Tenant
    {
        $subdomain = session('current_subdomain');
        
        if (!$subdomain) {
            return null;
        }

        return $this->tenantService->getTenantBySubdomain($subdomain);
    }

    /**
     * Vérifie si un utilisateur est authentifié dans un tenant
     *
     * @return bool
     */
    public function isAuthenticated(): bool
    {
        return Auth::check() && session()->has('current_subdomain');
    }

    /**
     * Déconnecte l'utilisateur et revient à la base principale
     */
    public function logout(): void
    {
        try {
            // Déconnecter l'utilisateur
            if (Auth::check()) {
                Auth::logout();
            }
            
            // Nettoyer la session
            if (session()->has('current_subdomain')) {
                session()->forget('current_subdomain');
            }
        } catch (\Exception $e) {
            // En cas d'erreur, continuer quand même
            Log::warning("Erreur lors de la déconnexion: " . $e->getMessage());
        } finally {
            // Toujours revenir à la base principale
            Config::set('database.default', 'mysql');
            DB::purge('tenant');
        }
    }
}

