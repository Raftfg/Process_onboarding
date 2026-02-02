<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Services\ActivationService;
use App\Services\TenantService;
use App\Rules\RecaptchaRule;

class OnboardingController extends Controller
{
    protected $activationService;
    protected $tenantService;

    public function __construct(ActivationService $activationService, TenantService $tenantService)
    {
        $this->activationService = $activationService;
        $this->tenantService = $tenantService;
    }

    /**
     * Page d'accueil
     */
    public function welcome()
    {
        Session::forget('onboarding_data');
        return view('onboarding.welcome');
    }

    /**
     * Affiche le formulaire initial (email + organisation + reCAPTCHA)
     */
    public function showInitialForm()
    {
        // S'assurer que la session est démarrée et que le token CSRF est disponible
        Session::start();
        
        Log::info('=== showInitialForm appelé ===', [
            'session_id' => Session::getId(),
            'csrf_token' => substr(csrf_token(), 0, 10) . '...',
        ]);
        
        return view('onboarding.initial-form');
    }

    /**
     * Traite les données initiales et redirige vers la page de chargement
     */
    public function storeInitialData(Request $request)
    {
        // Log immédiat pour vérifier que la méthode est appelée
        Log::info('=== storeInitialData appelé ===', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'has_csrf' => $request->has('_token'),
        ]);
        
        try {
            // Log des données reçues pour déboguer
            Log::info('Données reçues du formulaire initial', [
                'email' => $request->input('email'),
                'organization_name' => $request->input('organization_name'),
                'has_recaptcha' => $request->has('g-recaptcha-response'),
                'recaptcha_length' => strlen($request->input('g-recaptcha-response', '')),
                'all_inputs' => array_keys($request->all()),
            ]);

            $rules = [
                'email' => 'required|email|max:255',
                'organization_name' => 'required|string|max:255',
            ];

            // Ajouter la validation reCAPTCHA seulement si les clés sont configurées
            $recaptchaSiteKey = config('services.recaptcha.site_key');
            $recaptchaSecretKey = config('services.recaptcha.secret_key');
            
            Log::info('Vérification configuration reCAPTCHA', [
                'has_site_key' => !empty($recaptchaSiteKey),
                'has_secret_key' => !empty($recaptchaSecretKey),
                'has_recaptcha_input' => $request->has('g-recaptcha-response'),
                'recaptcha_value' => $request->input('g-recaptcha-response') ? 'present' : 'missing',
            ]);
            
            // TEMPORAIRE: Désactiver la validation reCAPTCHA pour tester
            // TODO: Réactiver après résolution du problème
            if (false && !empty($recaptchaSiteKey) && !empty($recaptchaSecretKey)) {
                $rules['g-recaptcha-response'] = ['required', new RecaptchaRule()];
                Log::info('Validation reCAPTCHA activée');
            } else {
                Log::info('Validation reCAPTCHA désactivée (test ou clés non configurées)');
            }

            $validated = $request->validate($rules);
            
            Log::info('Validation réussie, redirection vers loading');

            // Stocker les données en session ET en cache comme backup
            $onboardingData = [
                'email' => $validated['email'],
                'organization_name' => $validated['organization_name'],
            ];
            
            // Créer un token unique pour passer les données via l'URL
            $dataToken = \Illuminate\Support\Str::random(32);
            
            // Stocker dans le cache avec le token comme clé (expire en 5 minutes)
            \Illuminate\Support\Facades\Cache::put("onboarding_data_{$dataToken}", $onboardingData, now()->addMinutes(5));
            
            // Stocker aussi en session au cas où
            $request->session()->put('onboarding_data', $onboardingData);
            $request->session()->put('onboarding_data_token', $dataToken);
            
            Log::info('Données stockées en session et cache', [
                'session_id' => $request->session()->getId(),
                'data_token' => $dataToken,
                'onboarding_data' => $onboardingData,
            ]);

            // Forcer la sauvegarde de la session
            $request->session()->save();
            
            Log::info('Session sauvegardée, redirection vers loading avec token...', [
                'session_id_after_save' => $request->session()->getId(),
                'data_token' => $dataToken,
            ]);
            
            // Rediriger avec le token dans l'URL (query parameter) pour garantir la persistance
            $loadingUrl = route('onboarding.loading') . '?token=' . urlencode($dataToken);
            $response = redirect($loadingUrl)
                ->with('onboarding_data_temp', $onboardingData);
            
            Log::info('Réponse de redirection créée avec token dans URL');
            
            return $response;
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Retourner avec les erreurs de validation et les anciennes valeurs
            Log::warning('Erreur de validation lors du stockage des données initiales', [
                'errors' => $e->errors(),
                'input' => $request->except(['g-recaptcha-response', '_token']),
            ]);
            
            try {
                return redirect()->route('onboarding.start')
                    ->withErrors($e->errors())
                    ->withInput($request->except('g-recaptcha-response'));
            } catch (\Exception $redirectException) {
                Log::error('Erreur lors de la redirection après validation: ' . $redirectException->getMessage());
                // Retourner une réponse simple en cas d'erreur de redirection
                return response()->view('onboarding.initial-form', [
                    'errors' => $e->errors(),
                    'old' => $request->except('g-recaptcha-response'),
                ], 422);
            }
        } catch (\Exception $e) {
            Log::error('Erreur lors du stockage des données initiales: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            try {
                return redirect()->route('onboarding.start')
                    ->with('error', 'Une erreur est survenue. Veuillez rafraîchir la page et réessayer.')
                    ->withInput($request->except('g-recaptcha-response'));
            } catch (\Exception $redirectException) {
                Log::error('Erreur lors de la redirection après exception: ' . $redirectException->getMessage());
                // Retourner une réponse simple en cas d'erreur de redirection
                return response('Une erreur est survenue. Veuillez rafraîchir la page et réessayer.', 500);
            }
        }
    }

    /**
     * Affiche la page de chargement
     */
    public function showLoading(Request $request)
    {
        $token = $request->query('token');
        
        Log::info('=== showLoading appelé ===', [
            'has_token' => !empty($token),
            'token' => $token ? substr($token, 0, 10) . '...' : null,
            'has_onboarding_data' => Session::has('onboarding_data'),
            'onboarding_data' => Session::get('onboarding_data'),
            'has_temp_data' => Session::has('onboarding_data_temp'),
            'temp_data' => Session::get('onboarding_data_temp'),
            'session_id' => Session::getId(),
        ]);
        
        // Récupérer les données depuis le cache (via token), la session, ou les données flash
        $onboardingData = null;
        
        // 1. Essayer depuis le cache avec le token
        if ($token) {
            $onboardingData = \Illuminate\Support\Facades\Cache::get("onboarding_data_{$token}");
            if ($onboardingData) {
                Log::info('Données récupérées depuis le cache avec token', [
                    'token' => substr($token, 0, 10) . '...',
                    'email' => $onboardingData['email'] ?? null,
                ]);
                // Stocker en session pour les prochaines requêtes
                Session::put('onboarding_data', $onboardingData);
            }
        }
        
        // 2. Essayer depuis la session
        if (!$onboardingData) {
            $onboardingData = Session::get('onboarding_data');
            if ($onboardingData) {
                Log::info('Données récupérées depuis la session');
            }
        }
        
        // 3. Essayer depuis les données flash
        if (!$onboardingData && Session::has('onboarding_data_temp')) {
            $onboardingData = Session::get('onboarding_data_temp');
            Session::put('onboarding_data', $onboardingData);
            Session::forget('onboarding_data_temp');
            Log::info('Données restaurées depuis flash', [
                'onboarding_data' => $onboardingData,
            ]);
        }
        
        if (!$onboardingData) {
            Log::warning('Pas de données onboarding trouvées, redirection vers start');
            return redirect()->route('onboarding.start')
                ->with('error', 'Veuillez remplir le formulaire pour continuer.');
        }

        Log::info('Données onboarding trouvées', [
            'email' => $onboardingData['email'] ?? null,
            'organization_name' => $onboardingData['organization_name'] ?? null,
        ]);

        return view('onboarding.loading');
    }

    /**
     * Affiche la page de confirmation
     */
    public function showConfirmation()
    {
        $onboardingData = Session::get('onboarding_data');
        
        if (!$onboardingData) {
            return redirect()->route('onboarding.start');
        }

        return view('onboarding.confirmation', [
            'email' => $onboardingData['email'] ?? '',
            'organization_name' => $onboardingData['organization_name'] ?? '',
        ]);
    }

    /**
     * Affiche la page d'activation (email prérempli + mot de passe)
     */
    public function showActivation(Request $request, $token)
    {
        // Nettoyer le token (enlever les caractères invalides qui pourraient s'être glissés)
        // Les tokens sont des strings alphanumériques de 64 caractères
        $token = trim($token);
        
        // Si le token contient des caractères invalides, essayer de le nettoyer
        // Mais d'abord, vérifier s'il y a un problème avec l'URL
        if (strlen($token) < 32) {
            // Le token semble trop court ou corrompu
            Log::warning('Token d\'activation suspect (trop court)', [
                'token_length' => strlen($token),
                'token_preview' => substr($token, 0, 20),
                'full_url' => $request->fullUrl(),
            ]);
        }
        
        // Si le token est vide ou invalide, essayer de le récupérer depuis la query string
        if (empty($token) || strlen($token) < 32) {
            if ($request->has('token')) {
                $token = $request->query('token');
                $token = trim($token);
            }
        }
        
        if (empty($token) || strlen($token) < 32) {
            Log::warning('Token d\'activation vide ou invalide', [
                'url_token' => $request->route('token'),
                'query_token' => $request->query('token'),
                'token_length' => strlen($token ?? ''),
            ]);
            return view('onboarding.activation-invalid');
        }
        
        // Vérifier que le token est valide
        if (!$this->activationService->validateToken($token)) {
            $activation = $this->activationService->getActivationByToken($token);
            
            // Si le compte est déjà activé, rediriger gracieusement vers le dashboard
            if ($activation && $activation->isActivated()) {
                Log::info('Tentative d\'accès à un lien déjà activé, redirection vers dashboard', [
                    'email' => $activation->email,
                    'subdomain' => $activation->subdomain
                ]);

                // Créer un token temporaire pour l'authentification automatique sur le sous-domaine
                $autoLoginToken = \Illuminate\Support\Str::random(64);
                
                // Stocker le token dans la base de données principale
                \Illuminate\Support\Facades\DB::connection('mysql')->table('auto_login_tokens')->insert([
                    'token' => $autoLoginToken,
                    'user_id' => $activation->user_id ?? 1,
                    'subdomain' => $activation->subdomain,
                    'database_name' => $activation->database_name,
                    'expires_at' => now()->addMinutes(30),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Construire l'URL du dashboard
                if (config('app.env') === 'local') {
                    $port = parse_url(config('app.url', 'http://localhost:8000'), PHP_URL_PORT) ?? '8000';
                    $dashboardUrl = "http://{$activation->subdomain}.localhost:{$port}/dashboard?auto_login_token={$autoLoginToken}";
                } else {
                    $baseDomain = config('app.subdomain_base_domain', 'akasigroup.local');
                    $dashboardUrl = "https://{$activation->subdomain}.{$baseDomain}/dashboard?auto_login_token={$autoLoginToken}";
                }

                return redirect()->away($dashboardUrl);
            }

            if ($activation && $activation->isExpired()) {
                return view('onboarding.activation-expired');
            }
            
            Log::warning('Token d\'activation invalide', [
                'token_preview' => substr($token, 0, 10) . '...',
            ]);
            return view('onboarding.activation-invalid');
        }

        // Si le token est valide, récupérer l'activation pour afficher le formulaire
        $activation = $this->activationService->getActivationByToken($token);
        
        // Si pour une raison quelconque l'activation n'est pas trouvée malgré un token valide
        if (!$activation) {
            Log::warning('Activation non trouvée pour un token valide', [
                'token_preview' => substr($token, 0, 10) . '...',
            ]);
            return view('onboarding.activation-invalid');
        }
        
        // Récupérer l'email depuis le token ou depuis la query string
        $email = $request->query('email') ?? $activation->email ?? '';

        return view('onboarding.activation', [
            'token' => $token,
            'email' => $email,
            'organizationName' => $activation->organization_name ?? null,
        ]);
    }

    /**
     * Traite l'activation et crée le compte
     */
    public function activate(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        try {
            // Activer le compte (cela crée l'utilisateur et bascule vers la base du tenant)
            $result = $this->activationService->activateAccount(
                $validated['token'],
                $validated['password']
            );

            // S'assurer qu'on est bien sur la base du tenant
            if ($result['subdomain'] && $result['user']) {
                $subdomain = $result['subdomain'];
                
                // S'assurer que la base du tenant est active
                if ($result['database_name']) {
                    $this->tenantService->switchToTenantDatabase($result['database_name']);
                }
                
                // Stocker le sous-domaine en session pour le middleware
                Session::put('current_subdomain', $subdomain);
                
                // Recharger l'utilisateur depuis la base du tenant pour s'assurer qu'il est à jour
                $user = \App\Models\User::find($result['user']->id);
                
                if (!$user) {
                    throw new \Exception('Utilisateur non trouvé dans la base du tenant');
                }
                
                Log::info('Tentative de connexion utilisateur', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'database' => config('database.default'),
                    'current_db' => \Illuminate\Support\Facades\DB::connection()->getDatabaseName(),
                ]);
                
                // Connecter l'utilisateur automatiquement avec "remember"
                Auth::login($user, true);
                
                // Vérifier que la connexion a réussi
                if (!Auth::check()) {
                    Log::error('Échec de la connexion utilisateur après Auth::login');
                    throw new \Exception('Échec de la connexion automatique');
                }
                
                Log::info('Utilisateur connecté avec succès', [
                    'user_id' => Auth::id(),
                    'email' => Auth::user()->email,
                ]);
                
                // Sauvegarder la session AVANT de régénérer
                Session::save();
                
                // Régénérer la session pour sécuriser (après sauvegarde)
                $oldSessionId = Session::getId();
                Session::regenerate();
                $newSessionId = Session::getId();
                
                Log::info('Session régénérée', [
                    'old_session_id' => $oldSessionId,
                    'new_session_id' => $newSessionId,
                ]);
                
                // Sauvegarder à nouveau après régénération
                Session::save();
                
                // Vérifier à nouveau que l'utilisateur est toujours connecté
                if (!Auth::check()) {
                    Log::error('Utilisateur déconnecté après régénération de session');
                    throw new \Exception('Erreur lors de la connexion automatique');
                }
                


                // Créer un token temporaire pour l'authentification automatique sur le sous-domaine
                // (nécessaire car la session n'est pas partagée entre localhost et subdomain.localhost)
                // Utiliser la base de données principale au lieu du cache pour partager entre domaines
                $autoLoginToken = \Illuminate\Support\Str::random(64);
                
                // Stocker le token dans la base de données principale
                // IMPORTANT: Le token doit avoir une durée de vie suffisante pour permettre la navigation
                // dans le dashboard même si la session n'est pas partagée entre domaines
                // On utilise 30 minutes pour permettre une navigation confortable
                try {
                    $inserted = \Illuminate\Support\Facades\DB::connection('mysql')->table('auto_login_tokens')->insert([
                        'token' => $autoLoginToken,
                        'user_id' => $user->id,
                        'subdomain' => $subdomain,
                        'database_name' => $result['database_name'],
                        'expires_at' => now()->addMinutes(30), // Augmenté de 5 à 30 minutes
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    
                    // Vérifier que le token a bien été inséré
                    $tokenCheck = \Illuminate\Support\Facades\DB::connection('mysql')
                        ->table('auto_login_tokens')
                        ->where('token', $autoLoginToken)
                        ->first();
                    
                    Log::info('Token stocké dans la base de données', [
                        'token' => substr($autoLoginToken, 0, 10) . '...',
                        'user_id' => $user->id,
                        'subdomain' => $subdomain,
                        'database_name' => $result['database_name'],
                        'inserted' => $inserted,
                        'token_check' => $tokenCheck ? 'ok' : 'failed',
                        'expires_at' => now()->addMinutes(5)->toDateTimeString(),
                    ]);
                } catch (\Exception $e) {
                    Log::error('Erreur lors du stockage du token: ' . $e->getMessage(), [
                        'exception' => get_class($e),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    throw $e;
                }

                // Construire l'URL du dashboard avec le token d'authentification automatique
                if (config('app.env') === 'local') {
                    $port = parse_url(config('app.url', 'http://localhost:8000'), PHP_URL_PORT) ?? '8000';
                    $dashboardUrl = "http://{$subdomain}.localhost:{$port}/dashboard?auto_login_token={$autoLoginToken}";
                } else {
                    $baseDomain = config('app.subdomain_base_domain', 'akasigroup.local');
                    $dashboardUrl = "https://{$subdomain}.{$baseDomain}/dashboard?auto_login_token={$autoLoginToken}";
                }

                Log::info('Compte activé, utilisateur connecté et redirigé vers le dashboard', [
                    'email' => $validated['email'],
                    'user_id' => $user->id,
                    'subdomain' => $subdomain,
                    'dashboard_url' => $dashboardUrl,
                    'is_authenticated' => Auth::check(),
                    'current_user_id' => Auth::id(),
                    'session_id' => Session::getId(),
                    'password_changed_at' => $user->password_changed_at,
                    'token_preview' => substr($autoLoginToken, 0, 10) . '...',
                ]);

                // Forcer la sauvegarde de la session avant redirection
                Session::save();
                
                // Rediriger directement vers le dashboard sur le sous-domaine avec le token
                // Le middleware Authenticate vérifiera le token et connectera automatiquement l'utilisateur
                return redirect()->away($dashboardUrl)->with('success', 'Votre compte a été activé avec succès ! Bienvenue sur votre espace Akasi Group.');
            } else {
                throw new \Exception('Erreur lors de l\'activation : données incomplètes');
            }
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'activation: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Formater l'erreur pour l'utilisateur
            $userMessage = \App\Helpers\ErrorFormatter::formatException($e);
            
            return back()->withErrors([
                'activation' => $userMessage,
            ])->withInput();
        }
    }

    /**
     * Connexion automatique via token (utilisé après activation)
     */
    public function autoLogin(Request $request)
    {
        $token = $request->query('token');
        
        if (!$token) {
            return redirect()->route('login')->withErrors([
                'token' => 'Token de connexion manquant.',
            ]);
        }
        
        // Récupérer les informations du token depuis le cache
        $cacheKey = "auto_login_{$token}";
        $tokenData = \Illuminate\Support\Facades\Cache::get($cacheKey);
        
        if (!$tokenData) {
            return redirect()->route('login')->withErrors([
                'token' => 'Token de connexion invalide ou expiré.',
            ]);
        }
        
        try {
            $subdomain = $tokenData['subdomain'] ?? null;
            $databaseName = $tokenData['database_name'] ?? null;
            $userId = $tokenData['user_id'] ?? null;
            
            if (!$subdomain || !$databaseName || !$userId) {
                throw new \Exception('Données du token incomplètes');
            }
            
            // Basculer vers la base du tenant
            $this->tenantService->switchToTenantDatabase($databaseName);
            
            // Stocker le sous-domaine en session
            Session::put('current_subdomain', $subdomain);
            
            // Récupérer l'utilisateur depuis la base du tenant
            $user = \App\Models\User::find($userId);
            
            if (!$user) {
                throw new \Exception('Utilisateur non trouvé dans la base du tenant');
            }
            
            // Connecter l'utilisateur avec "remember" pour maintenir la session
            Auth::login($user, true);
            
            // Vérifier immédiatement que la connexion a réussi
            if (!Auth::check()) {
                throw new \Exception('Échec de la connexion automatique');
            }
            
            // Sauvegarder la session immédiatement
            Session::save();
            
            // Régénérer la session pour la sécurité
            Session::regenerate(true);
            
            // Vérifier que l'utilisateur est toujours connecté après régénération
            if (!Auth::check()) {
                throw new \Exception('Erreur lors de la connexion automatique');
            }
            
            // Sauvegarder à nouveau après régénération
            Session::save();
            
            // Supprimer le token du cache (usage unique) APRÈS avoir vérifié la connexion
            \Illuminate\Support\Facades\Cache::forget($cacheKey);
            
            // Construire l'URL complète du dashboard
            if (config('app.env') === 'local') {
                $port = parse_url(config('app.url', 'http://localhost:8000'), PHP_URL_PORT) ?? '8000';
                $dashboardUrl = "http://{$subdomain}.localhost:{$port}/dashboard";
            } else {
                $baseDomain = config('app.subdomain_base_domain', 'akasigroup.local');
                $dashboardUrl = "https://{$subdomain}.{$baseDomain}/dashboard";
            }
            
            // Forcer la sauvegarde de la session avant redirection
            Session::save();
            
            return redirect()->away($dashboardUrl)->with('success', 'Votre compte a été activé avec succès ! Bienvenue sur votre espace Akasi Group.');
            
        } catch (\Exception $e) {
            Log::error('Erreur lors de la connexion automatique: ' . $e->getMessage());
            
            // Formater l'erreur pour l'utilisateur
            $userMessage = \App\Helpers\ErrorFormatter::formatException($e);
            
            return redirect()->route('login')->withErrors([
                'token' => $userMessage,
            ]);
        }
    }
}
