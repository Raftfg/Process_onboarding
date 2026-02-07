<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LogoutController extends Controller
{
    protected $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Déconnecte l'utilisateur
     */
    public function __invoke(Request $request)
    {
        // Extraire le sous-domaine avant de déconnecter
        $subdomain = null;
        $host = $request->getHost();
        $parts = explode('.', $host);
        
        // En local: format subdomain.localhost
        if (config('app.env') === 'local' && count($parts) >= 2 && $parts[1] === 'localhost') {
            $subdomain = $parts[0];
        } elseif (count($parts) >= 3) {
            $subdomain = $parts[0];
        }
        
        // Fallback: utiliser la session
        if (!$subdomain) {
            $subdomain = session('current_subdomain');
        }

        // Créer une activité de déconnexion avant de déconnecter
        if (Auth::check()) {
            try {
                $this->dashboardService->createActivity(
                    Auth::id(),
                    'logout',
                    'Déconnexion du système',
                    ['ip' => $request->ip()]
                );
            } catch (\Exception $e) {
                Log::warning('Impossible de créer l\'activité de déconnexion: ' . $e->getMessage());
            }
        }

        // Déconnecter l'utilisateur
        Auth::logout();

        // Invalider la session
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Revenir à la base principale
        \Illuminate\Support\Facades\Config::set('database.default', 'mysql');
        \Illuminate\Support\Facades\DB::purge('tenant');
        session()->forget('current_subdomain');

        Log::info('Utilisateur déconnecté', ['subdomain' => $subdomain]);

        // Rediriger vers la page de login du sous-domaine si disponible, sinon vers la page d'accueil
        if ($subdomain) {
            if (config('app.env') === 'local') {
                $port = $request->getPort();
                $loginUrl = "http://{$subdomain}.localhost:{$port}/login";
            } else {
                $baseDomain = config('app.brand_domain');
                $loginUrl = "https://{$subdomain}.{$baseDomain}/login";
            }
            return redirect($loginUrl)->with('success', 'Vous avez été déconnecté avec succès.');
        }

        return redirect('/')->with('success', 'Vous avez été déconnecté avec succès.');
    }
}
