<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Authenticate extends Middleware
{
    /**
     * Handle an incoming request.
     * Surcharge pour s'assurer que le modèle User est correctement configuré
     */
    public function handle($request, \Closure $next, ...$guards)
    {
        // S'assurer que le modèle User est correctement configuré pour le tenant
        // Le middleware DetectTenant devrait déjà avoir fait cela, mais on le fait ici aussi pour être sûr
        $subdomain = $this->extractSubdomain($request);
        
        if ($subdomain) {
            // Le middleware DetectTenant devrait déjà avoir configuré cela,
            // mais on le fait ici aussi pour être sûr
            Config::set('auth.providers.users.model', \App\Models\Tenant\User::class);
            
            // S'assurer que la connexion de base de données est correcte
            // Le middleware DetectTenant devrait déjà avoir basculé vers la base du tenant
            // Mais on vérifie ici pour être sûr
            try {
                $currentConnection = \Illuminate\Support\Facades\DB::connection()->getName();
                if ($currentConnection !== 'tenant') {
                    // Si on n'est pas sur la connexion tenant, essayer de basculer
                    $tenantService = app(\App\Services\TenantService::class);
                    $databaseName = $tenantService->getTenantDatabase($subdomain);
                    if ($databaseName) {
                        $tenantService->switchToTenantDatabase($databaseName);
                        // Reconfigurer le modèle User après le switch
                        Config::set('auth.providers.users.model', \App\Models\Tenant\User::class);
                    }
                }
            } catch (\Exception $e) {
                // En cas d'erreur, continuer quand même
                \Illuminate\Support\Facades\Log::warning("Erreur lors de la vérification de la connexion tenant: " . $e->getMessage());
            }
        }
        
        // Si on a un sous-domaine et qu'un utilisateur est en session,
        // vérifier qu'il existe toujours dans la base du tenant
        if ($subdomain && Auth::check()) {
            try {
                $user = Auth::user();
                // Vérifier que l'utilisateur existe toujours dans la base du tenant
                if ($user) {
                    $userExists = \App\Models\Tenant\User::where('id', $user->id)->exists();
                    if (!$userExists) {
                        // L'utilisateur n'existe plus dans la base du tenant, déconnecter
                        Auth::logout();
                        session()->forget('current_subdomain');
                        return redirect(subdomain_url($subdomain, '/login'))
                            ->with('error', 'Votre session a expiré. Veuillez vous reconnecter.');
                    }
                }
            } catch (\Exception $e) {
                // En cas d'erreur, déconnecter l'utilisateur
                Auth::logout();
                session()->forget('current_subdomain');
            }
        }
        
        // Appeler la méthode parent pour vérifier l'authentification
        // Si l'utilisateur n'est pas authentifié, cela redirigera vers login
        try {
            return parent::handle($request, $next, ...$guards);
        } catch (\Illuminate\Auth\AuthenticationException $e) {
            // Si l'authentification échoue, rediriger vers login avec le sous-domaine
            if ($subdomain) {
                return redirect(subdomain_url($subdomain, '/login'))
                    ->with('error', 'Vous devez être connecté pour accéder à cette page.');
            }
            throw $e;
        }
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson()) {
            return null;
        }

        // Récupérer le sous-domaine
        $subdomain = $this->extractSubdomain($request);

        if ($subdomain) {
            return subdomain_url($subdomain, '/login');
        }

        return route('login');
    }

    /**
     * Extrait le sous-domaine depuis la requête
     */
    protected function extractSubdomain(Request $request): ?string
    {
        // En développement local, le sous-domaine peut être passé en paramètre
        if (config('app.env') === 'local' && $request->has('subdomain')) {
            return $request->get('subdomain');
        }

        // Essayer depuis la session
        if (session('current_subdomain')) {
            return session('current_subdomain');
        }

        // Essayer d'extraire depuis le host
        $host = $request->getHost();
        $parts = explode('.', $host);
        
        if (count($parts) >= 2 && $parts[1] === 'localhost') {
            return $parts[0];
        } elseif (count($parts) >= 3) {
            return $parts[0];
        }

        return null;
    }
}
