<?php

namespace App\Http\Middleware;

use App\Services\TenantCustomizationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ApplyTenantCustomization
{
    protected $customizationService;

    public function __construct(TenantCustomizationService $customizationService)
    {
        $this->customizationService = $customizationService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Ne pas appliquer sur les routes admin
        if ($request->is('admin/*')) {
            return $next($request);
        }

        // Vérifier que nous sommes sur une base tenant
        $currentDatabase = \Illuminate\Support\Facades\DB::connection()->getDatabaseName();
        $mainDatabase = config('database.connections.mysql.database');
        
        // Si on est sur la base principale, ne pas charger les settings
        if ($currentDatabase === $mainDatabase) {
            // On est sur la base principale, ne pas charger les settings
            View::share([
                'tenantBranding' => $this->customizationService->getDefaultBranding(),
                'tenantLayout' => $this->customizationService->getDefaultLayout(),
                'tenantMenu' => $this->customizationService->getDefaultMenu(),
                'tenantCssVariables' => $this->customizationService->getDefaultCssVariables(),
            ]);
            return $next($request);
        }

        try {
            // Vérifier que la table existe avant d'essayer de l'utiliser
            $tableExists = \Illuminate\Support\Facades\Schema::hasTable('tenant_settings');
            
            if (!$tableExists) {
                // Table n'existe pas, utiliser les valeurs par défaut
                View::share([
                    'tenantBranding' => $this->customizationService->getDefaultBranding(),
                    'tenantLayout' => $this->customizationService->getDefaultLayout(),
                    'tenantMenu' => $this->customizationService->getDefaultMenu(),
                    'tenantCssVariables' => $this->customizationService->getDefaultCssVariables(),
                ]);
                return $next($request);
            }

            // Partager les settings avec toutes les vues
            $branding = $this->customizationService->getBranding();
            $layout = $this->customizationService->getLayout();
            $menu = $this->customizationService->getMenu();
            $cssVariables = $this->customizationService->getCssVariables();

            View::share([
                'tenantBranding' => $branding,
                'tenantLayout' => $layout,
                'tenantMenu' => $menu,
                'tenantCssVariables' => $cssVariables,
            ]);
        } catch (\Exception $e) {
            // En cas d'erreur (table n'existe pas, etc.), utiliser les valeurs par défaut
            \Illuminate\Support\Facades\Log::warning('Erreur lors du chargement des settings de personnalisation: ' . $e->getMessage(), [
                'current_database' => $currentDatabase,
                'exception' => get_class($e),
            ]);
            View::share([
                'tenantBranding' => $this->customizationService->getDefaultBranding(),
                'tenantLayout' => $this->customizationService->getDefaultLayout(),
                'tenantMenu' => $this->customizationService->getDefaultMenu(),
                'tenantCssVariables' => $this->customizationService->getDefaultCssVariables(),
            ]);
        }

        return $next($request);
    }
}
