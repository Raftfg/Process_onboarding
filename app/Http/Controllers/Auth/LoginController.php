<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\DashboardService;
use App\Services\TenantService;

class LoginController extends Controller
{
    protected $tenantService;
    protected $dashboardService;

    public function __construct(TenantService $tenantService, DashboardService $dashboardService)
    {
        $this->tenantService = $tenantService;
        $this->dashboardService = $dashboardService;
    }

    /**
     * Affiche le formulaire de connexion
     */
    public function showLoginForm(Request $request)
    {
        // Récupérer le sous-domaine depuis le host
        $subdomain = null;
        $host = $request->getHost();
        $parts = explode('.', $host);
        
        // En local: format subdomain.localhost
        // En production: format subdomain.domain.tld
        if (config('app.env') === 'local' && count($parts) >= 2 && $parts[1] === 'localhost') {
            $subdomain = $parts[0];
        } elseif (count($parts) >= 3) {
            $subdomain = $parts[0];
        }

        return view('auth.login', ['subdomain' => $subdomain]);
    }

    /**
     * Traite la tentative de connexion
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Récupérer le sous-domaine depuis le host
        $subdomain = null;
        $host = $request->getHost();
        $parts = explode('.', $host);
        
        // En local: format subdomain.localhost
        // En production: format subdomain.domain.tld
        if (config('app.env') === 'local' && count($parts) >= 2 && $parts[1] === 'localhost') {
            $subdomain = $parts[0];
        } elseif (count($parts) >= 3) {
            $subdomain = $parts[0];
        }
        
        // Le middleware DetectTenant devrait déjà avoir basculé vers la base du tenant
        // Mais on s'assure que c'est bien le cas
        if ($subdomain) {
            // Nettoyer le cache pour forcer la recherche en base
            $this->tenantService->clearTenantCache($subdomain);
            
            // Vérifier si on est déjà sur la base du tenant
            $currentDatabase = \Illuminate\Support\Facades\DB::connection()->getDatabaseName();
            $expectedDatabase = $this->tenantService->getTenantDatabase($subdomain);
            
            Log::info("Tentative de connexion", [
                'subdomain' => $subdomain,
                'current_database' => $currentDatabase,
                'expected_database' => $expectedDatabase
            ]);
            
            // Si on n'est pas sur la bonne base, basculer
            if ($expectedDatabase && $currentDatabase !== $expectedDatabase) {
                $this->tenantService->switchToTenantDatabase($expectedDatabase);
                session(['current_subdomain' => $subdomain]);
                Log::info("Basculement vers la base tenant: {$expectedDatabase}");
            } elseif (!$expectedDatabase) {
                Log::warning("Base de données non trouvée pour le sous-domaine: {$subdomain}", [
                    'subdomain' => $subdomain,
                    'current_database' => $currentDatabase
                ]);
                return back()->withErrors([
                    'email' => 'Sous-domaine invalide ou non configuré. Veuillez vérifier l\'URL.',
                ])->onlyInput('email');
            }
        } else {
            Log::warning("Tentative de connexion sans sous-domaine", [
                'host' => $request->getHost(),
                'url' => $request->fullUrl()
            ]);
        }

        // Tenter la connexion (sur la base du tenant si sous-domaine présent)
        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            // S'assurer que le sous-domaine est stocké dans la session
            if ($subdomain) {
                session(['current_subdomain' => $subdomain]);
            }

            $user = Auth::user();
            Log::info('Utilisateur connecté: ' . $user->email . ' (Base: ' . \Illuminate\Support\Facades\DB::connection()->getDatabaseName() . ')');
            
            // Créer une activité de connexion
            try {
                $this->dashboardService->createActivity(
                    $user->id,
                    'login',
                    'Connexion au système',
                    ['ip' => $request->ip(), 'user_agent' => $request->userAgent()]
                );
            } catch (\Exception $e) {
                Log::warning('Impossible de créer l\'activité de connexion: ' . $e->getMessage());
            }
            
            // Si l'utilisateur n'a jamais changé son mot de passe, le rediriger vers le changement
            if ($user->password_changed_at === null) {
                return redirect()->route('password.change');
            }

            // Rediriger vers le dashboard avec le sous-domaine dans l'URL (sans paramètre subdomain)
            if (config('app.env') === 'local' && $subdomain) {
                $redirectUrl = "http://{$subdomain}.localhost:8000/dashboard";
            } else {
                if ($subdomain) {
                    $baseDomain = config('app.subdomain_base_domain', 'medkey.local');
                    $redirectUrl = "https://{$subdomain}.{$baseDomain}/dashboard";
                } else {
                    $redirectUrl = route('dashboard');
                }
            }

            Log::info('Redirection vers le dashboard', [
                'url' => $redirectUrl,
                'subdomain' => $subdomain,
                'user_id' => Auth::id()
            ]);

            // Utiliser redirect() normal au lieu de redirect()->away() pour préserver la session
            return redirect($redirectUrl);
        }

        // Log de l'échec de connexion pour le débogage
        Log::warning('Échec de connexion', [
            'email' => $credentials['email'],
            'subdomain' => $subdomain,
            'database' => \Illuminate\Support\Facades\DB::connection()->getDatabaseName()
        ]);

        return back()->withErrors([
            'email' => 'Les identifiants fournis ne correspondent pas à nos enregistrements.',
        ])->onlyInput('email');
    }

    /**
     * Déconnecte l'utilisateur
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Revenir à la base principale
        \Illuminate\Support\Facades\Config::set('database.default', 'mysql');
        \Illuminate\Support\Facades\DB::purge('tenant');
        session()->forget('current_subdomain');

        return redirect('/');
    }
}
