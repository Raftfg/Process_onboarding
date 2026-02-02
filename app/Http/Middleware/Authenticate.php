<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

class Authenticate extends Middleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string[]  ...$guards
     * @return mixed
     */
    public function handle($request, Closure $next, ...$guards)
    {
        // Configuration dynamique du domaine de session
        // On n'injecte .localhost que si on n'utilise pas une adresse IP
        // Cela permet de supporter 127.0.0.1 tout en permettant le partage sur localhost
        if (config('app.env') === 'local' && !config('session.domain')) {
            $host = $request->getHost();
            if (!filter_var($host, FILTER_VALIDATE_IP) && ($host === 'localhost' || str_ends_with($host, '.localhost'))) {
                config(['session.domain' => '.localhost']);
            }
        }
        
        // Extraire le sous-domaine depuis l'URL ou la session
        $subdomain = null;
        $host = $request->getHost();
        $parts = explode('.', $host);
        
        // En local: format subdomain.localhost
        if (config('app.env') === 'local' && count($parts) >= 2 && $parts[1] === 'localhost') {
            $subdomain = $parts[0];
        } elseif (count($parts) >= 3) {
            // En production: format subdomain.domain.tld
            $subdomain = $parts[0];
        }
        
        // Fallback: utiliser la session
        if (!$subdomain && session('current_subdomain')) {
            $subdomain = session('current_subdomain');
        }

        // IMPORTANT: Si on est sur un sous-domaine et une route protégée (comme /dashboard),
        // basculer vers la base du tenant AVANT de vérifier l'authentification
        if ($subdomain) {
            $tenantService = app(\App\Services\TenantService::class);
            
            if ($tenantService->tenantExists($subdomain)) {
                $databaseName = $tenantService->getTenantDatabase($subdomain);
                
                if ($databaseName) {
                    $originalConnection = \Illuminate\Support\Facades\Config::get('database.default');
                    
                    try {
                        // Basculer vers la base du tenant si on n'y est pas déjà
                        if ($originalConnection !== 'tenant') {
                            $tenantService->switchToTenantDatabase($databaseName);
                            \Illuminate\Support\Facades\Log::info('Basculement vers base tenant dans Authenticate', [
                                'subdomain' => $subdomain,
                                'database' => $databaseName,
                            ]);
                        }
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error("Erreur lors du basculement vers la base tenant dans Authenticate: " . $e->getMessage());
                    }
                }
            }
        }

        // Vérifier l'authentification AVANT d'appeler la méthode parente pour avoir plus de contrôle
        $guards = empty($guards) ? [null] : $guards;
        $isAuthenticated = false;
        
        foreach ($guards as $guard) {
            if (\Illuminate\Support\Facades\Auth::guard($guard)->check()) {
                $isAuthenticated = true;
                \Illuminate\Support\Facades\Log::info('Utilisateur authentifié dans middleware', [
                    'guard' => $guard ?? 'default',
                    'user_id' => \Illuminate\Support\Facades\Auth::guard($guard)->id(),
                    'subdomain' => $subdomain,
                ]);
                break;
            }
        }
        
        // Si l'utilisateur n'est pas authentifié, vérifier s'il y a un token d'authentification automatique
        // IMPORTANT: Ne pas logger à chaque requête pour éviter le spam dans les logs
        if (!$isAuthenticated && $request->has('auto_login_token')) {
            $token = $request->query('auto_login_token');
            
            \Illuminate\Support\Facades\Log::info('Token détecté dans l\'URL, récupération depuis la base de données', [
                'token_preview' => substr($token, 0, 10) . '...',
                'token_length' => strlen($token),
                'subdomain' => $subdomain,
            ]);
            
            // Récupérer le token depuis la base de données principale
            // IMPORTANT: Ne pas vérifier l'expiration ici pour permettre la reconnexion même si le token est proche de l'expiration
            // On vérifiera l'expiration après avoir récupéré le token
            $tokenRecord = \Illuminate\Support\Facades\DB::connection('mysql')
                ->table('auto_login_tokens')
                ->where('token', $token)
                ->first();
            
            // Log pour diagnostic
            \Illuminate\Support\Facades\Log::info('Vérification du token d\'authentification automatique', [
                'token' => substr($token, 0, 10) . '...',
                'token_record' => $tokenRecord ? 'présent' : 'absent',
                'subdomain' => $subdomain,
                'token_expires_at' => $tokenRecord->expires_at ?? null,
                'now' => now()->toDateTimeString(),
                'token_expired' => $tokenRecord && $tokenRecord->expires_at <= now(),
            ]);
            
            // Vérifier l'expiration après récupération
            if ($tokenRecord && $tokenRecord->expires_at <= now()) {
                \Illuminate\Support\Facades\Log::warning('Token expiré', [
                    'token_preview' => substr($token, 0, 10) . '...',
                    'expires_at' => $tokenRecord->expires_at,
                    'now' => now()->toDateTimeString(),
                ]);
                $tokenRecord = null; // Marquer comme invalide
            }
            
            // Vérifier que le token existe et que le sous-domaine correspond
            if ($tokenRecord) {
                \Illuminate\Support\Facades\Log::info('Token trouvé, vérification du sous-domaine', [
                    'token_subdomain' => $tokenRecord->subdomain,
                    'request_subdomain' => $subdomain,
                    'match' => $tokenRecord->subdomain === $subdomain,
                ]);
            }
            
            if ($tokenRecord && $tokenRecord->subdomain === $subdomain) {
                try {
                    // S'assurer qu'on est sur la bonne base de données
                    if ($tokenRecord->database_name) {
                        $tenantService = app(\App\Services\TenantService::class);
                        $tenantService->switchToTenantDatabase($tokenRecord->database_name);
                    }
                    
                    // Récupérer l'utilisateur
                    $user = \App\Models\User::find($tokenRecord->user_id);
                    
                    if ($user) {
                        // Connecter l'utilisateur
                        \Illuminate\Support\Facades\Auth::login($user, true);
                        

                        
                        // IMPORTANT: Ne PAS supprimer le token maintenant
                        // Le token sera conservé dans l'URL pour permettre la reconnexion si la session n'est pas partagée
                        // Le token sera supprimé uniquement après confirmation que l'utilisateur est authentifié ET que la session est partagée
                        
                        // Sauvegarder le sous-domaine en session
                        session(['current_subdomain' => $subdomain]);
                        
                        // Sauvegarder la session AVANT de régénérer
                        session()->save();
                        
                        // IMPORTANT: Régénérer la session pour la sécurité
                        $oldSessionId = session()->getId();
                        session()->regenerate(true);
                        $newSessionId = session()->getId();
                        
                        // Vérifier que l'utilisateur est toujours connecté après régénération
                        if (!\Illuminate\Support\Facades\Auth::check()) {
                            \Illuminate\Support\Facades\Log::error('Utilisateur déconnecté après régénération de session dans middleware', [
                                'user_id' => $user->id,
                                'old_session_id' => $oldSessionId,
                                'new_session_id' => $newSessionId,
                            ]);
                            // Reconnecter l'utilisateur
                            \Illuminate\Support\Facades\Auth::login($user, true);
                        }
                        
                        // Le domaine de session est géré par la config
                        
                        // Sauvegarder la session APRÈS régénération et configuration du domaine
                        session()->save();
                        
                        // IMPORTANT: Forcer la sauvegarde du cookie de session avec le bon domaine
                        // Cela garantit que le cookie est partagé entre tous les sous-domaines
                        $response = response();
                        $cookie = cookie(
                            config('session.cookie'),
                            session()->getId(),
                            config('session.lifetime'),
                            config('session.path', '/'),
                            config('session.domain', '.localhost'),
                            config('session.secure', false),
                            config('session.http_only', true),
                            false,
                            config('session.same_site', 'lax')
                        );
                        
                        \Illuminate\Support\Facades\Log::info('Utilisateur connecté automatiquement via token', [
                            'user_id' => $user->id,
                            'email' => $user->email,
                            'subdomain' => $subdomain,
                            'is_authenticated' => \Illuminate\Support\Facades\Auth::check(),
                            'session_id' => session()->getId(),
                            'auth_user_id' => \Illuminate\Support\Facades\Auth::id(),
                            'request_url' => $request->url(),
                            'request_route' => $request->route()?->getName(),
                            'is_dashboard' => $request->is('dashboard') || $request->route()?->getName() === 'dashboard',
                            'session_domain' => config('session.domain'),
                        ]);
                        
                        // IMPORTANT: Ne PAS rediriger si on est déjà sur le dashboard ou une route du dashboard
                        // Cela évite la boucle de redirection infinie
                        // Si l'utilisateur est authentifié et qu'on est sur le dashboard, continuer vers le contrôleur
                        $isDashboardRoute = $request->route()?->getName() === 'dashboard' 
                            || $request->is('dashboard') 
                            || $request->is('dashboard/*')
                            || str_starts_with($request->path(), 'dashboard');
                            
                        if ($isDashboardRoute) {
                            \Illuminate\Support\Facades\Log::info('Utilisateur authentifié via token, déjà sur dashboard - continuation vers contrôleur (pas de redirection)', [
                                'user_id' => $user->id,
                                'subdomain' => $subdomain,
                                'route' => $request->route()?->getName(),
                                'path' => $request->path(),
                            ]);
                            
                            // Injecter manuellement le message de succès dans la session du sous-domaine
                            // pour compenser la perte de session cross-domain lors du passage de 127.0.0.1 à subdomain.localhost
                            if (!session()->has('success')) {
                                session()->flash('success', 'Votre compte a été activé avec succès ! Bienvenue sur votre espace Akasi Group.');
                                session()->save();
                            }

                            // Ne PAS rediriger, continuer vers le contrôleur
                            // Le token sera supprimé de l'URL lors de la vérification après token
                            // On continue l'exécution du middleware sans redirection
                            \Illuminate\Support\Facades\Log::info('Message de succès injecté manuellement dans la session du sous-domaine.');
                        } else {
                            // Si on n'est pas sur le dashboard, rediriger vers le dashboard avec le token
                            if (config('app.env') === 'local') {
                                $port = parse_url(config('app.url', 'http://localhost:8000'), PHP_URL_PORT) ?? '8000';
                                $dashboardUrl = "http://{$subdomain}.localhost:{$port}/dashboard?auto_login_token={$token}";
                            } else {
                                $baseDomain = config('app.subdomain_base_domain', 'akasigroup.local');
                                $dashboardUrl = "https://{$subdomain}.{$baseDomain}/dashboard?auto_login_token={$token}";
                            }
                            
                            \Illuminate\Support\Facades\Log::info('Redirection vers dashboard après connexion automatique', [
                                'dashboard_url' => $dashboardUrl,
                                'user_id' => $user->id,
                                'subdomain' => $subdomain,
                            ]);
                            
                            return redirect()->away($dashboardUrl)->with('success', 'Votre compte a été activé avec succès ! Bienvenue sur votre espace Akasi Group.');
                        }
                    } else {
                        \Illuminate\Support\Facades\Log::warning('Utilisateur non trouvé avec le token', [
                            'user_id' => $tokenRecord->user_id,
                            'subdomain' => $subdomain,
                            'database_name' => $tokenRecord->database_name,
                        ]);
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Erreur lors de la connexion automatique via token: ' . $e->getMessage(), [
                        'exception' => get_class($e),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            } else {
                \Illuminate\Support\Facades\Log::warning('Token invalide, expiré ou sous-domaine ne correspond pas', [
                    'token_record' => $tokenRecord ? 'présent' : 'absent',
                    'token_subdomain' => $tokenRecord->subdomain ?? null,
                    'request_subdomain' => $subdomain,
                    'token_expires_at' => $tokenRecord->expires_at ?? null,
                    'now' => now()->toDateTimeString(),
                ]);
            }
        }
        
        // Vérifier si l'utilisateur est maintenant authentifié (peut-être via token)
        $isAuthenticatedAfterToken = false;
        foreach ($guards as $guard) {
            if (\Illuminate\Support\Facades\Auth::guard($guard)->check()) {
                $isAuthenticatedAfterToken = true;
                
                // Si l'utilisateur est authentifié et qu'il y a un token dans l'URL
                if ($request->has('auto_login_token')) {
                    $token = $request->query('auto_login_token');
                    
                    // IMPORTANT: Ne supprimer le token QUE si on est sur le dashboard
                    // Cela permet de garder le token pour les autres routes si nécessaire
                    $isDashboardRoute = $request->route()?->getName() === 'dashboard' 
                        || $request->is('dashboard') 
                        || $request->is('dashboard/*')
                        || str_starts_with($request->path(), 'dashboard');
                    
                    // IMPORTANT: Ne PAS supprimer le token immédiatement
                    // Le token doit être conservé pour permettre la reconnexion si la session n'est pas partagée
                    // On ne supprimera le token que si l'utilisateur est authentifié ET que la session est partagée
                    // Pour vérifier que la session est partagée, on vérifie si l'utilisateur reste authentifié
                    // entre les requêtes. Si oui, on peut supprimer le token. Sinon, on le garde.
                    
                    // Pour l'instant, on garde le token dans l'URL pour toutes les routes du dashboard
                    // Le token sera supprimé uniquement après confirmation que la session est partagée
                    \Illuminate\Support\Facades\Log::info('Token conservé pour permettre la reconnexion (session peut ne pas être partagée)', [
                        'user_id' => \Illuminate\Support\Facades\Auth::guard($guard)->id(),
                        'route' => $request->route()?->getName(),
                        'path' => $request->path(),
                        'is_dashboard' => $isDashboardRoute,
                    ]);
                    
                    // Ne pas supprimer le token de l'URL non plus
                    // Il restera dans l'URL pour permettre la reconnexion si nécessaire
                }
                
                \Illuminate\Support\Facades\Log::info('Utilisateur authentifié après vérification token', [
                    'guard' => $guard ?? 'default',
                    'user_id' => \Illuminate\Support\Facades\Auth::guard($guard)->id(),
                ]);
                break;
            }
        }
        
        // Si l'utilisateur n'est pas authentifié ET qu'il n'y a pas de token dans l'URL, alors seulement rediriger vers login
        if (!$isAuthenticatedAfterToken && !$request->has('auto_login_token')) {
            \Illuminate\Support\Facades\Log::warning('Utilisateur non authentifié dans middleware - Redirection vers login', [
                'subdomain' => $subdomain,
                'guards' => $guards,
                'session_id' => session()->getId(),
                'has_auto_login_token' => false,
                'url' => $request->fullUrl(),
                'route' => $request->route()?->getName(),
                'query_params' => $request->query(),
            ]);
        } else if ($isAuthenticatedAfterToken) {
            \Illuminate\Support\Facades\Log::info('Utilisateur authentifié, passage au contrôleur', [
                'user_id' => \Illuminate\Support\Facades\Auth::id(),
                'subdomain' => $subdomain,
                'route' => $request->route()?->getName(),
            ]);
        } else if ($request->has('auto_login_token')) {
            // Il y a un token mais l'utilisateur n'est pas authentifié
            // Cela signifie que le token n'a pas fonctionné, mais on ne doit pas rediriger vers login
            // car le token pourrait être valide mais la session n'est pas partagée
            // On laisse le code continuer pour que le token soit vérifié à nouveau
            \Illuminate\Support\Facades\Log::info('Token présent mais utilisateur non authentifié - vérification en cours', [
                'subdomain' => $subdomain,
                'has_token' => true,
            ]);
        }

        // IMPORTANT: Si l'utilisateur n'est pas authentifié mais qu'il y a un token dans l'URL,
        // on ne doit PAS appeler authenticate() car cela redirigera vers login
        // Le token devrait reconnecter l'utilisateur lors de la prochaine requête
        // MAIS: Si l'utilisateur est authentifié, on peut appeler authenticate() pour vérifier
        if ($isAuthenticatedAfterToken) {
            // L'utilisateur est authentifié, vérifier avec authenticate() pour s'assurer qu'il peut accéder
            try {
                $this->authenticate($request, $guards);
            } catch (\Illuminate\Auth\AuthenticationException $e) {
                // Si authenticate() lance une exception, cela signifie que l'utilisateur n'est pas vraiment authentifié
                // Dans ce cas, si on a un token, on ne doit pas rediriger vers login
                if ($request->has('auto_login_token')) {
                    \Illuminate\Support\Facades\Log::warning('Exception d\'authentification mais token présent - continuation', [
                        'subdomain' => $subdomain,
                        'has_token' => true,
                    ]);
                    // Ne pas relancer l'exception, continuer vers le contrôleur
                } else {
                    throw $e;
                }
            }
        } else if (!$request->has('auto_login_token')) {
            // Pas de token et pas authentifié, appeler authenticate() qui redirigera vers login
            $this->authenticate($request, $guards);
        }
        // Si on a un token mais pas authentifié, on ne fait rien et on continue
        // Le token sera vérifié lors de la prochaine requête

        return $next($request);
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson()) {
            return null;
        }

        // Extraire le sous-domaine depuis l'URL ou la session
        $subdomain = null;
        $host = $request->getHost();
        $parts = explode('.', $host);
        
        // En local: format subdomain.localhost
        if (config('app.env') === 'local' && count($parts) >= 2 && $parts[1] === 'localhost') {
            $subdomain = $parts[0];
        } elseif (count($parts) >= 3) {
            // En production: format subdomain.domain.tld
            $subdomain = $parts[0];
        }
        
        // Fallback: utiliser la session
        if (!$subdomain && session('current_subdomain')) {
            $subdomain = session('current_subdomain');
        }

        // Construire l'URL de login avec le sous-domaine dans l'hostname (sans paramètre)
        if ($subdomain) {
            if (config('app.env') === 'local') {
                $port = parse_url(config('app.url', 'http://localhost:8000'), PHP_URL_PORT) ?? '8000';
                return "http://{$subdomain}.localhost:{$port}/login";
            } else {
                $baseDomain = config('app.subdomain_base_domain', 'akasigroup.local');
                return "https://{$subdomain}.{$baseDomain}/login";
            }
        }

        // Si pas de sous-domaine, utiliser l'URL par défaut
        return route('login');
    }
}
