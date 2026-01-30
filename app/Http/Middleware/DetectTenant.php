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

        // Vérifier si le tenant existe
        if (!$this->tenantService->tenantExists($subdomain)) {
            // Si le tenant n'existe pas, continuer avec la base principale
            return $next($request);
        }

        // Récupérer la base de données du tenant
        $databaseName = $this->tenantService->getTenantDatabase($subdomain);
        
        if ($databaseName) {
            try {
                // Basculer vers la base de données du tenant
                $this->tenantService->switchToTenantDatabase($databaseName);
                
                // Stocker le sous-domaine dans la session pour utilisation ultérieure
                session(['current_subdomain' => $subdomain]);
            } catch (\Exception $e) {
                \Log::error("Erreur lors du basculement vers la base tenant: " . $e->getMessage());
                // En cas d'erreur, continuer avec la base principale
            }
        }

        return $next($request);
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

        // En production, extraire depuis le host
        $host = $request->getHost();
        $parts = explode('.', $host);
        
        // Si on a au moins 3 parties (subdomain.domain.tld), prendre la première
        if (count($parts) >= 3) {
            return $parts[0];
        }

        return null;
    }
}
