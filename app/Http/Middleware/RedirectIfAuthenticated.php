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
                // Ajouter le sous-domaine si disponible
                $subdomain = null;
                if (config('app.env') === 'local' && $request->has('subdomain')) {
                    $subdomain = $request->get('subdomain');
                } elseif (session('current_subdomain')) {
                    $subdomain = session('current_subdomain');
                } else {
                    // Essayer d'extraire depuis le host
                    $host = $request->getHost();
                    $parts = explode('.', $host);
                    if (count($parts) >= 2 && $parts[0] !== 'localhost' && $parts[0] !== '127' && $parts[0] !== 'www') {
                        $subdomain = $parts[0];
                    }
                }
                
                if ($subdomain) {
                    return redirect(subdomain_url($subdomain, '/dashboard'));
                }
                
                return redirect()->route('dashboard');
            }
        }

        return $next($request);
    }
}
