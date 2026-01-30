<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TenantAuthService;
use Illuminate\Http\Request;

class LogoutController extends Controller
{
    protected $tenantAuthService;

    public function __construct(TenantAuthService $tenantAuthService)
    {
        $this->tenantAuthService = $tenantAuthService;
    }

    /**
     * Déconnecte l'utilisateur
     */
    public function __invoke(Request $request)
    {
        try {
            // Récupérer le sous-domaine avant la déconnexion
            $subdomain = session('current_subdomain');
            
            // Si pas de sous-domaine dans la session, essayer de l'extraire depuis l'URL
            if (!$subdomain) {
                $host = $request->getHost();
                $parts = explode('.', $host);
                if (count($parts) >= 2 && $parts[1] === 'localhost') {
                    $subdomain = $parts[0];
                } elseif (count($parts) >= 3) {
                    $subdomain = $parts[0];
                }
            }

            // Utiliser TenantAuthService pour la déconnexion
            $this->tenantAuthService->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // Rediriger vers la page de login du tenant ou la page d'accueil
            if ($subdomain) {
                return redirect(subdomain_url($subdomain, '/login'))
                    ->with('status', 'Vous avez été déconnecté avec succès.');
            }

            return redirect('/')
                ->with('status', 'Vous avez été déconnecté avec succès.');
        } catch (\Exception $e) {
            // En cas d'erreur, forcer la déconnexion et rediriger
            \Illuminate\Support\Facades\Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            
            // Revenir à la base principale
            \Illuminate\Support\Facades\Config::set('database.default', 'mysql');
            \Illuminate\Support\Facades\DB::purge('tenant');
            
            return redirect('/')
                ->with('status', 'Vous avez été déconnecté avec succès.');
        }
    }
}
