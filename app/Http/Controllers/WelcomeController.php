<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use App\Services\TenantService;
use App\Models\OnboardingSession;
use Illuminate\Support\Facades\Log;

class WelcomeController extends Controller
{
    protected $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    public function index(Request $request)
    {
        // Récupérer le sous-domaine depuis l'URL
        $host = $request->getHost();
        $parts = explode('.', $host);
        
        // En local, le format est: subdomain.localhost
        // En production, le format est: subdomain.domain.com
        if (count($parts) >= 2 && $parts[1] === 'localhost') {
            $subdomain = $parts[0];
        } elseif (count($parts) >= 3) {
            // En production, extraire le sous-domaine
            $subdomain = $parts[0];
        } else {
            // Fallback: essayer depuis le paramètre (pour compatibilité)
            $subdomain = $request->get('subdomain');
        }
        
        // Si l'utilisateur est déjà connecté, rediriger directement vers le dashboard
        if (Auth::check()) {
            if ($subdomain) {
                return redirect(subdomain_url($subdomain, '/dashboard'));
            }
            return redirect()->route('dashboard');
        }
        
        // Si c'est une redirection après onboarding et que l'utilisateur n'est pas connecté,
        // essayer de le reconnecter automatiquement
        if ($request->has('welcome') && $subdomain) {
            $this->attemptAutoLogin($subdomain);
            
            // Vérifier à nouveau si la connexion a réussi
            if (Auth::check()) {
                return redirect(subdomain_url($subdomain, '/dashboard'));
            }
        }
        
        // Vérifier si c'est une redirection après onboarding
        $isWelcome = $request->has('welcome');
        
        return view('welcome', [
            'isWelcome' => $isWelcome,
            'subdomain' => $subdomain
        ]);
    }

    /**
     * Tente de reconnecter l'utilisateur après onboarding
     */
    protected function attemptAutoLogin(string $subdomain): void
    {
        try {
            // Récupérer les informations d'onboarding
            $onboarding = OnboardingSession::where('subdomain', $subdomain)
                ->where('status', 'completed')
                ->first();
            
            if (!$onboarding) {
                return;
            }

            // Basculer vers la base du tenant
            $databaseName = $this->tenantService->getTenantDatabase($subdomain);
            if ($databaseName) {
                $this->tenantService->switchToTenantDatabase($databaseName);
                session(['current_subdomain' => $subdomain]);
            }

            // Récupérer l'utilisateur depuis la base du tenant
            $user = \App\Models\User::where('email', $onboarding->admin_email)->first();
            
            if ($user) {
                // Connecter l'utilisateur
                Auth::login($user, true);
                Session::regenerate();
                
                Log::info("Reconnexion automatique réussie pour: {$onboarding->admin_email}");
            }
        } catch (\Exception $e) {
            Log::error('Erreur lors de la reconnexion automatique: ' . $e->getMessage());
        }
    }
}
