<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RootDomain
{
    /**
     * Handle an incoming request.
     * 
     * Vérifie que la requête est bien sur le domaine racine (pas de sous-domaine)
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $parts = explode('.', $host);
        
        // Déterminer si on est en local ou sur Laravel Cloud
        $isLocal = config('app.env') === 'local';
        $isLaravelCloud = str_contains($host, 'laravel.cloud');
        
        // En local: vérifier que ce n'est pas un sous-domaine
        if ($isLocal) {
            // Format local: subdomain.localhost ou localhost ou 127.0.0.1
            // Si on a au moins 2 parties et la deuxième est "localhost", c'est un sous-domaine
            if (count($parts) >= 2 && $parts[1] === 'localhost') {
                // C'est un sous-domaine, rediriger vers la page d'accueil
                return redirect('/');
            }
            // Si c'est localhost, 127.0.0.1, ou un autre format sans sous-domaine, laisser passer
        } elseif ($isLaravelCloud) {
            // Sur Laravel Cloud: app-name.laravel.cloud (3 parties) = domaine principal
            // subdomain.app-name.laravel.cloud (4+ parties) = sous-domaine
            if (count($parts) >= 4) {
                // C'est un sous-domaine, rediriger vers la page d'accueil
                return redirect('/');
            }
            // Si c'est le domaine principal (3 parties), laisser passer
        } else {
            // En production: vérifier que le host est exactement le domaine de base
            $baseDomain = config('app.subdomain_base_domain', 'akasigroup.local');
            
            // Si le host contient plus de parties que le domaine de base, c'est un sous-domaine
            $baseParts = explode('.', $baseDomain);
            if (count($parts) > count($baseParts)) {
                // C'est un sous-domaine, rediriger vers la page d'accueil
                return redirect('/');
            }
            
            // Si le host correspond exactement au domaine de base, laisser passer
            if ($host === $baseDomain) {
                return $next($request);
            }
            
            // Si le host se termine par le domaine de base mais n'est pas exactement égal,
            // c'est probablement un sous-domaine (ex: subdomain.akasigroup.local)
            if (str_ends_with($host, '.' . $baseDomain)) {
                return redirect('/');
            }
            
            // Pour les autres cas (domaines différents), laisser passer
            // (peut être utile pour le développement ou des configurations spéciales)
        }
        
        return $next($request);
    }
}
