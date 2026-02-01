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

        // Stocker le sous-domaine dans la requête pour utilisation ultérieure
        $request->attributes->set('tenant_subdomain', $subdomain);
        // Si la session est déjà démarrée (ce qui ne devrait pas être le cas si on déplace le middleware),
        // on pourrait le stocker. Mais pour l'instant, on évite d'écrire en session ici.

        $routeName = $request->route()?->getName();
        
        $isRootLoginRoute = in_array($routeName, ['root.login', 'root.login.find', 'root.login.subdomains', 'root.login.select']) ||
                           $request->is('root-login') ||
                           $request->is('root-login/*');
        
        $isAdminRoute = $request->is('admin') || 
                        $request->is('admin/*');
        
        // Ne PAS basculer pour les routes root-login et admin
        if (!$isRootLoginRoute && !$isAdminRoute) {
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
