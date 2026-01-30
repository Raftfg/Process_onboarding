<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\TenantService;

class LoginController extends Controller
{
    protected $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    /**
     * Affiche le formulaire de connexion
     */
    public function showLoginForm(Request $request)
    {
        // Récupérer le sous-domaine si disponible
        $subdomain = null;
        if (config('app.env') === 'local' && $request->has('subdomain')) {
            $subdomain = $request->get('subdomain');
        } else {
            $host = $request->getHost();
            $parts = explode('.', $host);
            if (count($parts) >= 3) {
                $subdomain = $parts[0];
            }
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

        // Récupérer le sous-domaine pour basculer vers la bonne base
        $subdomain = $request->input('subdomain');
        
        if ($subdomain) {
            // Basculer vers la base du tenant
            $databaseName = $this->tenantService->getTenantDatabase($subdomain);
            if ($databaseName) {
                $this->tenantService->switchToTenantDatabase($databaseName);
                session(['current_subdomain' => $subdomain]);
            }
        }

        // Tenter la connexion
        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            Log::info('Utilisateur connecté: ' . Auth::user()->email);

            // Rediriger vers le dashboard
            if ($subdomain) {
                return redirect(subdomain_url($subdomain, '/dashboard'));
            }

            return redirect()->route('dashboard');
        }

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
