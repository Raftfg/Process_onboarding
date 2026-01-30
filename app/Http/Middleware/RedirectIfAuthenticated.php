<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            // Vérifier si l'utilisateur est authentifié
            // IMPORTANT: Ne vérifier que si on est sur une route de login/welcome
            // pour éviter les boucles de redirection
            $path = $request->path();
            $isLoginRoute = in_array($path, ['login', 'welcome']);
            
            if (!$isLoginRoute) {
                // Si on n'est pas sur une route de login, ne pas vérifier l'authentification
                // pour éviter les boucles
                continue;
            }
            
            if (Auth::guard($guard)->check()) {
                try {
                    $user = Auth::guard($guard)->user();
                    
                    // Si l'utilisateur existe, rediriger vers le dashboard
                    if ($user) {
                        // Extraire le sous-domaine
                        $subdomain = null;
                        if (config('app.env') === 'local' && $request->has('subdomain')) {
                            $subdomain = $request->get('subdomain');
                        } elseif (session('current_subdomain')) {
                            $subdomain = session('current_subdomain');
                        } else {
                            // Essayer d'extraire depuis le host
                            $host = $request->getHost();
                            $parts = explode('.', $host);
                            if (count($parts) >= 2 && $parts[1] === 'localhost') {
                                $subdomain = $parts[0];
                            } elseif (count($parts) >= 3) {
                                $subdomain = $parts[0];
                            }
                        }
                        
                        if ($subdomain) {
                            return redirect(subdomain_url($subdomain, '/dashboard'));
                        } else {
                            return redirect()->route('dashboard');
                        }
                    }
                } catch (\Exception $e) {
                    // En cas d'erreur, déconnecter l'utilisateur et continuer
                    Auth::guard($guard)->logout();
                }
            }
        }

        return $next($request);
    }
}
