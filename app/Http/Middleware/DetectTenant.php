<?php

namespace App\Http\Middleware;

use App\Services\TenantService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DetectTenant
{
    protected $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Récupérer le sous-domaine
        $subdomain = $this->extractSubdomain($request);
        
        if (!$subdomain) {
            // Si pas de sous-domaine, continuer avec la base principale
            return $next($request);
        }

        // Stocker le sous-domaine dans la session pour utilisation ultérieure
        session(['current_subdomain' => $subdomain]);

        // IMPORTANT: Ne PAS basculer vers la base du tenant ici pour :
        // 1. Les routes protégées par 'auth' (comme /dashboard) - car cela casse l'authentification
        // 2. Les routes de login (/login) - car l'utilisateur n'est pas encore authentifié
        // 3. Les routes admin (/admin/*) - car elles doivent toujours utiliser la base principale
        // Le basculement sera fait dans les contrôleurs après vérification de l'authentification.
        
        $routeName = $request->route()?->getName();
        $isAuthRoute = in_array($routeName, ['dashboard']) || 
                       $request->is('dashboard') || 
                       $request->is('dashboard/*');
        
        $isLoginRoute = in_array($routeName, ['login']) || 
                        $request->is('login') || 
                        $request->is('login/*');
        
        $isAdminRoute = $request->is('admin') || 
                        $request->is('admin/*');
        
        // Ne basculer que pour les routes non protégées, non login et non admin (comme /welcome)
        if (!$isAuthRoute && !$isLoginRoute && !$isAdminRoute) {
            // Vérifier si le tenant existe
            if ($this->tenantService->tenantExists($subdomain)) {
                // Récupérer la base de données du tenant
                $databaseName = $this->tenantService->getTenantDatabase($subdomain);
                
                if ($databaseName) {
                    try {
                        // Basculer vers la base de données du tenant
                        $this->tenantService->switchToTenantDatabase($databaseName);
                    } catch (\Exception $e) {
                        \Log::error("Erreur lors du basculement vers la base tenant: " . $e->getMessage());
                        // En cas d'erreur, continuer avec la base principale
                    }
                }
            }
        }

        return $next($request);
    }

    /**
     * Extrait le sous-domaine depuis la requête
     */
    protected function extractSubdomain(Request $request): ?string
    {
        $host = $request->getHost();
        $parts = explode('.', $host);
        
        // En développement local, format: subdomain.localhost
        // En production, format: subdomain.domain.tld
        if (config('app.env') === 'local') {
            // Format local: hopital-cotonou.localhost
            // Prendre la première partie si on a au moins 2 parties et la deuxième est "localhost"
            if (count($parts) >= 2 && $parts[1] === 'localhost') {
                return $parts[0];
            }
        } else {
            // En production, extraire depuis le host
            // Format: subdomain.domain.tld
            if (count($parts) >= 3) {
                return $parts[0];
            }
        }

        return null;
    }
}
