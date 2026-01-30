<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TenantAuthService;
use App\Services\TenantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LoginController extends Controller
{
    protected $tenantAuthService;
    protected $tenantService;

    public function __construct(TenantAuthService $tenantAuthService, TenantService $tenantService)
    {
        $this->tenantAuthService = $tenantAuthService;
        $this->tenantService = $tenantService;
    }

    /**
     * Affiche le formulaire de connexion
     */
    public function showLoginForm(Request $request)
    {
        // Récupérer le sous-domaine depuis l'URL
        $subdomain = $this->extractSubdomain($request);

        // Récupérer l'email depuis la query string si présent (venant de la sélection de domaine)
        $email = $request->query('email');

        return view('auth.login', [
            'subdomain' => $subdomain,
            'email' => $email,
        ]);
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

        // Récupérer le sous-domaine
        $subdomain = $this->extractSubdomain($request);
        
        if (!$subdomain) {
            return back()->withErrors([
                'email' => 'Impossible de déterminer le tenant. Veuillez accéder via votre sous-domaine.',
            ])->onlyInput('email');
        }

        try {
            Log::info('Tentative de connexion', [
                'email' => $credentials['email'],
                'subdomain' => $subdomain,
                'ip' => $request->ip(),
            ]);

            // Authentifier via TenantAuthService
            $user = $this->tenantAuthService->authenticate(
                $credentials['email'],
                $credentials['password'],
                $subdomain,
                $request->boolean('remember')
            );

            if ($user) {
                $request->session()->regenerate();
                Log::info('Utilisateur connecté avec succès', [
                    'email' => $user->email,
                    'tenant' => $subdomain,
                    'user_id' => $user->id,
                ]);

                // Rediriger vers le dashboard
                return redirect(subdomain_url($subdomain, '/dashboard'));
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Erreur de validation lors de la connexion', [
                'email' => $credentials['email'],
                'subdomain' => $subdomain,
                'errors' => $e->errors(),
            ]);
            return back()->withErrors($e->errors())->onlyInput('email');
        } catch (\Exception $e) {
            Log::error('Erreur lors de la connexion', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'email' => $credentials['email'],
                'subdomain' => $subdomain,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return back()->withErrors([
                'email' => 'Une erreur est survenue lors de la connexion. Veuillez vérifier les logs pour plus de détails.',
            ])->onlyInput('email');
        }

        return back()->withErrors([
            'email' => 'Les identifiants fournis ne correspondent pas à nos enregistrements.',
        ])->onlyInput('email');
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

        // Extraire depuis le host
        $host = $request->getHost();
        $parts = explode('.', $host);
        
        // En local, le format est: subdomain.localhost
        // En production, le format est: subdomain.domain.com
        if (count($parts) >= 2 && $parts[1] === 'localhost') {
            return $parts[0];
        } elseif (count($parts) >= 3) {
            // En production, extraire le sous-domaine
            return $parts[0];
        }

        return null;
    }
}
