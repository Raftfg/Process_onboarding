<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\TenantService;

class SubdomainHomeController extends Controller
{
    protected $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    /**
     * Gère l'accès à la racine d'un sous-domaine
     * Redirige vers /login si non authentifié, vers /dashboard si authentifié
     */
    public function index(Request $request)
    {
        // Récupérer le sous-domaine depuis le host
        $host = $request->getHost();
        $parts = explode('.', $host);
        
        $subdomain = null;
        // En local: format subdomain.localhost
        // En production: format subdomain.domain.tld
        if (config('app.env') === 'local' && count($parts) >= 2 && $parts[1] === 'localhost') {
            $subdomain = $parts[0];
        } elseif (count($parts) >= 3) {
            $subdomain = $parts[0];
        }
        
        // Si pas de sous-domaine, on est probablement sur le domaine racine
        // Dans ce cas, laisser la route onboarding.welcome gérer
        if (!$subdomain) {
            return redirect()->route('onboarding.welcome');
        }
        
        // Vérifier que le tenant existe
        if (!$this->tenantService->tenantExists($subdomain)) {
            // Tenant n'existe pas, rediriger vers le domaine racine
            if (config('app.env') === 'local') {
                return redirect('http://localhost:8000/');
            } else {
                $baseDomain = config('app.brand_domain');
                return redirect("https://{$baseDomain}/");
            }
        }
        
        // Si l'utilisateur est authentifié, rediriger vers le dashboard
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        
        // Sinon, rediriger vers la page de connexion
        return redirect()->route('login');
    }
}
