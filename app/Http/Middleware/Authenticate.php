<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

class Authenticate extends Middleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string[]  ...$guards
     * @return mixed
     */
    public function handle($request, Closure $next, ...$guards)
    {
        // Extraire le sous-domaine depuis l'URL ou la session
        $subdomain = null;
        $host = $request->getHost();
        $parts = explode('.', $host);
        
        // En local: format subdomain.localhost
        if (config('app.env') === 'local' && count($parts) >= 2 && $parts[1] === 'localhost') {
            $subdomain = $parts[0];
        } elseif (count($parts) >= 3) {
            // En production: format subdomain.domain.tld
            $subdomain = $parts[0];
        }
        
        // Fallback: utiliser la session
        if (!$subdomain && session('current_subdomain')) {
            $subdomain = session('current_subdomain');
        }

        // IMPORTANT: Si on est sur un sous-domaine et une route protégée (comme /dashboard),
        // basculer vers la base du tenant AVANT de vérifier l'authentification
        if ($subdomain) {
            $tenantService = app(\App\Services\TenantService::class);
            
            if ($tenantService->tenantExists($subdomain)) {
                $databaseName = $tenantService->getTenantDatabase($subdomain);
                
                if ($databaseName) {
                    $originalConnection = \Illuminate\Support\Facades\Config::get('database.default');
                    
                    try {
                        // Basculer vers la base du tenant si on n'y est pas déjà
                        if ($originalConnection !== 'tenant') {
                            $tenantService->switchToTenantDatabase($databaseName);
                        }
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error("Erreur lors du basculement vers la base tenant dans Authenticate: " . $e->getMessage());
                    }
                }
            }
        }

        // Appeler la méthode parente pour vérifier l'authentification
        // La méthode parente appelle $this->authenticate($request, $guards)
        $this->authenticate($request, $guards);

        return $next($request);
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson()) {
            return null;
        }

        // Extraire le sous-domaine depuis l'URL ou la session
        $subdomain = null;
        $host = $request->getHost();
        $parts = explode('.', $host);
        
        // En local: format subdomain.localhost
        if (config('app.env') === 'local' && count($parts) >= 2 && $parts[1] === 'localhost') {
            $subdomain = $parts[0];
        } elseif (count($parts) >= 3) {
            // En production: format subdomain.domain.tld
            $subdomain = $parts[0];
        }
        
        // Fallback: utiliser la session
        if (!$subdomain && session('current_subdomain')) {
            $subdomain = session('current_subdomain');
        }

        // Construire l'URL de login avec le sous-domaine dans l'hostname (sans paramètre)
        if ($subdomain) {
            if (config('app.env') === 'local') {
                $port = parse_url(config('app.url', 'http://localhost:8000'), PHP_URL_PORT) ?? '8000';
                return "http://{$subdomain}.localhost:{$port}/login";
            } else {
                $baseDomain = config('app.subdomain_base_domain', 'medkey.local');
                return "https://{$subdomain}.{$baseDomain}/login";
            }
        }

        // Si pas de sous-domaine, utiliser l'URL par défaut
        return route('login');
    }
}
