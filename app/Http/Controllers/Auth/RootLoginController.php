<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TenantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class RootLoginController extends Controller
{
    protected $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    /**
     * Affiche le formulaire de connexion racine
     */
    public function showLoginForm(Request $request)
    {
        // Vérifier qu'on est bien sur le domaine racine (pas de sous-domaine)
        $host = $request->getHost();
        $parts = explode('.', $host);
        
        $isSubdomain = false;
        if (config('app.env') === 'local') {
            if (count($parts) >= 2 && $parts[1] === 'localhost') {
                $isSubdomain = true;
            }
        } else {
            $baseDomain = config('app.subdomain_base_domain', 'akasigroup.local');
            $baseParts = explode('.', $baseDomain);
            if (count($parts) > count($baseParts)) {
                $isSubdomain = true;
            }
        }
        
        if ($isSubdomain) {
            return redirect('/');
        }
        
        return view('auth.root-login');
    }

    /**
     * Traite la soumission de l'email et recherche les sous-domaines
     */
    public function findSubdomains(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = $request->input('email');
        
        Log::info("Recherche de sous-domaines pour l'email: {$email}");
        
        // Rechercher les sous-domaines associés à cet email
        $subdomains = $this->tenantService->findSubdomainsByEmail($email);
        
        if (empty($subdomains)) {
            return back()->withErrors([
                'email' => 'Aucun espace trouvé pour cet email. Veuillez vérifier votre adresse email.',
            ])->withInput();
        }
        
        Log::info("Sous-domaines trouvés pour {$email}: " . count($subdomains));
        
        return redirect()->route('root.login.subdomains', ['email' => $email]);
    }

    /**
     * Affiche la liste des sous-domaines trouvés
     */
    public function showSubdomains(Request $request)
    {
        $email = $request->query('email') ?? Session::get('root_login_email');
        $subdomains = Session::get('root_login_subdomains');
        
        // Si la session est vide but on a l'email, re-récupérer les sous-domaines
        if (!$subdomains && $email) {
            Log::info("Session vide sur root-subdomains, recalcul pour {$email}");
            $subdomains = $this->tenantService->findSubdomainsByEmail($email);
            if (!empty($subdomains)) {
                Session::put('root_login_email', $email);
                Session::put('root_login_subdomains', $subdomains);
            }
        }
        
        if (!$email || !$subdomains || empty($subdomains)) {
            Log::warning("Échec de récupération des sous-domaines pour l'affichage", [
                'has_email' => !empty($email),
                'has_subdomains' => !empty($subdomains)
            ]);
            return redirect()->route('root.login')->withErrors([
                'email' => 'Session expirée ou email invalide. Veuillez réessayer.',
            ]);
        }
        
        return view('auth.root-subdomains', [
            'email' => $email,
            'subdomains' => $subdomains,
        ]);
    }

    /**
     * Redirige vers la page de login du sous-domaine sélectionné
     */
    public function selectSubdomain(Request $request)
    {
        $request->validate([
            'subdomain' => ['required', 'string'],
        ]);

        $subdomain = $request->input('subdomain');
        $email = $request->query('email') ?? Session::get('root_login_email');
        $subdomains = Session::get('root_login_subdomains');
        
        // Sécurité supplémentaire: si toujours pas de subdomains, re-récupérer
        if (!$subdomains && $email) {
            $subdomains = $this->tenantService->findSubdomainsByEmail($email);
        }
        
        // Vérifier que le sous-domaine est valide et dans la liste
        if (!$subdomains || !is_array($subdomains)) {
            return redirect()->route('root.login')->withErrors([
                'email' => 'Session expirée. Veuillez réessayer.',
            ]);
        }
        
        $validSubdomain = collect($subdomains)->first(function ($item) use ($subdomain) {
            return $item['subdomain'] === $subdomain;
        });
        
        if (!$validSubdomain) {
            return redirect()->route('root.login.subdomains')->withErrors([
                'subdomain' => 'Sous-domaine invalide.',
            ]);
        }
        
        // Vérifier que le tenant existe
        if (!$this->tenantService->tenantExists($subdomain)) {
            return redirect()->route('root.login.subdomains')->withErrors([
                'subdomain' => 'Ce sous-domaine n\'existe pas ou n\'est plus actif.',
            ]);
        }
        
        // Construire l'URL de login du sous-domaine
        if (config('app.env') === 'local') {
            $port = parse_url(config('app.url', 'http://localhost:8000'), PHP_URL_PORT) ?? '8000';
            $loginUrl = "http://{$subdomain}.localhost:{$port}/login";
        } else {
            $baseDomain = config('app.subdomain_base_domain', 'akasigroup.local');
            $loginUrl = "https://{$subdomain}.{$baseDomain}/login";
        }
        
        // Ajouter l'email en paramètre pour pré-remplir le formulaire (optionnel)
        if ($email) {
            $loginUrl .= '?email=' . urlencode($email);
        }
        
        Log::info("Redirection vers le sous-domaine: {$loginUrl}");
        
        // Nettoyer la session
        Session::forget('root_login_email');
        Session::forget('root_login_subdomains');
        
        return redirect()->away($loginUrl);
    }
}
