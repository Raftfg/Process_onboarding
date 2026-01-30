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

        // Extraire le sous-domaine depuis l'URL ou la session
        $subdomain = null;
        $host = $request->getHost();
        $parts = explode('.', $host);
        
        // En local: format subdomain.localhost
        if (config('app.env') === 'local' && count($parts) >= 2 && $parts[1] === 'localhost') {
            $subdomain = $parts[0];
        } elseif (count($parts) >= 3) {
            $subdomain = $parts[0];
        }
        
        // Fallback: utiliser la session
        if (!$subdomain && session('current_subdomain')) {
            $subdomain = session('current_subdomain');
        }

        // IMPORTANT: Pour éviter les boucles, ne vérifier l'authentification que si on est sur un sous-domaine
        // et si on peut basculer vers la base du tenant
        if ($subdomain) {
            $tenantService = app(\App\Services\TenantService::class);
            
            // Vérifier si le tenant existe
            if ($tenantService->tenantExists($subdomain)) {
                $databaseName = $tenantService->getTenantDatabase($subdomain);
                
                if ($databaseName) {
                    // Sauvegarder la connexion actuelle
                    $originalConnection = \Illuminate\Support\Facades\Config::get('database.default');
                    
                    try {
                        // Basculer vers la base du tenant temporairement pour vérifier l'authentification
                        if ($originalConnection !== 'tenant') {
                            $tenantService->switchToTenantDatabase($databaseName);
                        }
                        
                        // Vérifier l'authentification
                        foreach ($guards as $guard) {
                            if (Auth::guard($guard)->check()) {
                                // Remettre la connexion originale
                                if ($originalConnection !== 'tenant') {
                                    \Illuminate\Support\Facades\Config::set('database.default', $originalConnection);
                                    \Illuminate\Support\Facades\DB::purge('tenant');
                                }
                                
                                // Construire l'URL du dashboard avec le sous-domaine dans l'URL (sans paramètre)
                                if (config('app.env') === 'local') {
                                    $dashboardUrl = "http://{$subdomain}.localhost:8000/dashboard";
                                } else {
                                    $baseDomain = config('app.subdomain_base_domain', 'medkey.local');
                                    $dashboardUrl = "https://{$subdomain}.{$baseDomain}/dashboard";
                                }
                                
                                return redirect()->away($dashboardUrl);
                            }
                        }
                        
                        // Remettre la connexion originale
                        if ($originalConnection !== 'tenant') {
                            \Illuminate\Support\Facades\Config::set('database.default', $originalConnection);
                            \Illuminate\Support\Facades\DB::purge('tenant');
                        }
                    } catch (\Exception $e) {
                        // En cas d'erreur, remettre la connexion originale et continuer
                        if (isset($originalConnection) && $originalConnection !== 'tenant') {
                            \Illuminate\Support\Facades\Config::set('database.default', $originalConnection);
                            \Illuminate\Support\Facades\DB::purge('tenant');
                        }
                        \Illuminate\Support\Facades\Log::error("Erreur dans RedirectIfAuthenticated: " . $e->getMessage());
                    }
                }
            }
        }

        return $next($request);
    }
}
