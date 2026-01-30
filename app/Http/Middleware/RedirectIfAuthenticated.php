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
            if (Auth::guard($guard)->check()) {
                // Rediriger vers le dashboard au lieu de la page d'accueil
                $dashboardUrl = route('dashboard');
                
                // Ajouter le sous-domaine si disponible
                $subdomain = null;
                if (config('app.env') === 'local' && $request->has('subdomain')) {
                    $subdomain = $request->get('subdomain');
                } elseif (session('current_subdomain')) {
                    $subdomain = session('current_subdomain');
                }
                
                if ($subdomain) {
                    $dashboardUrl .= (strpos($dashboardUrl, '?') !== false ? '&' : '?') . 'subdomain=' . $subdomain;
                }
                
                return redirect($dashboardUrl);
            }
        }

        return $next($request);
    }
}
