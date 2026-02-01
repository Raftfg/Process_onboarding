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
        // Récupérer le sous-domaine depuis le host
        $host = $request->getHost();
        $parts = explode('.', $host);
        
        // En local: format subdomain.localhost
        // En production: format subdomain.domain.tld
        if (config('app.env') === 'local' && count($parts) >= 2 && $parts[1] === 'localhost') {
            $subdomain = $parts[0];
        } elseif (count($parts) >= 3) {
            $subdomain = $parts[0];
        } else {
            $subdomain = null;
        }
        
        // Si l'utilisateur est déjà connecté, rediriger directement vers le dashboard
        // MAIS seulement si on a un sous-domaine valide
        if (Auth::check() && $subdomain) {
            // Construire l'URL du dashboard avec le sous-domaine dans l'URL (sans paramètre)
            if (config('app.env') === 'local') {
                $dashboardUrl = "http://{$subdomain}.localhost:8000/dashboard";
            } else {
                $baseDomain = config('app.subdomain_base_domain', 'akasigroup.local');
                $dashboardUrl = "https://{$subdomain}.{$baseDomain}/dashboard";
            }
            
            return redirect()->away($dashboardUrl);
        }
        
        // Si c'est une redirection après onboarding, rediriger vers la page de connexion
        // au lieu de tenter une connexion automatique
        if ($request->has('welcome') && $subdomain && !Auth::check()) {
            if (config('app.env') === 'local') {
                $loginUrl = "http://{$subdomain}.localhost:8000/login";
            } else {
                $baseDomain = config('app.subdomain_base_domain', 'akasigroup.local');
                $loginUrl = "https://{$subdomain}.{$baseDomain}/login";
            }
            return redirect($loginUrl)->with('success', 'Onboarding terminé avec succès. Veuillez vous connecter.');
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
            // S'assurer qu'on utilise la base principale pour chercher l'onboarding
            \Illuminate\Support\Facades\Config::set('database.default', 'mysql');
            \Illuminate\Support\Facades\DB::purge('tenant');
            
            // Récupérer les informations d'onboarding depuis la base principale
            $onboarding = OnboardingSession::on('mysql')
                ->where('subdomain', $subdomain)
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
