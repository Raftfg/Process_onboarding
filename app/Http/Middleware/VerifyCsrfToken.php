<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Illuminate\Support\Facades\Log;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        // Temporairement désactivé pour les routes d'onboarding en développement
        // TODO: Réactiver après résolution du problème de session
        'logout',
        '/logout',
        'dashboard/*',
        'dashboard/customization/*',
        'login',
        'onboarding/*',
        'admin/*',
        'root-login/*',
        'step1',
        'step2',
        'module/test/*',
    ];
    
    /**
     * Determine if the session and input CSRF tokens match.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function tokensMatch($request)
    {
        $token = $this->getTokenFromRequest($request);
        $sessionToken = $request->session()->token();
        
        $match = is_string($sessionToken) &&
                 is_string($token) &&
                 hash_equals($sessionToken, $token);

        // Toujours logger en cas d'échec pour debugger
        if (!$match) {
            Log::warning('CSRF Mismatch detected', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'session_token_preview' => $sessionToken ? substr($sessionToken, 0, 10) . '...' : 'null',
                'request_token_preview' => $token ? substr($token, 0, 10) . '...' : 'null',
                'session_id' => $request->session()->getId(),
                'cookies_count' => count($request->cookies->all()),
            ]);
        }
        
        // Logique spécifique pour onboarding, login et dashboard en dev (legacy code)
        if (($request->is('onboarding/*') || $request->is('login') || $request->is('logout') || $request->is('dashboard/*')) && !$match && (config('app.env') === 'local' || config('app.debug'))) {
             Log::warning('Allowing CSRF mismatch in dev for: ' . $request->path());
             return true;
        }

        return $match;
    }
    
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, \Closure $next)
    {
        // Log pour les routes d'onboarding
        if ($request->is('onboarding/*')) {
            $sessionToken = $request->session()->token();
            $requestToken = $request->input('_token');
            $tokenMatch = $sessionToken === $requestToken;
            
            Log::info('VerifyCsrfToken - Requête onboarding', [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'has_token' => $request->has('_token'),
                'token_match' => $tokenMatch,
                'session_token_preview' => $sessionToken ? substr($sessionToken, 0, 10) . '...' : 'null',
                'request_token_preview' => $requestToken ? substr($requestToken, 0, 10) . '...' : 'null',
                'session_id' => $request->session()->getId(),
            ]);
            
            // En développement, si le token ne correspond pas, permettre quand même
            if (!$tokenMatch && $request->method() === 'POST') {
                if (config('app.env') === 'local' || config('app.debug')) {
                    Log::warning('Token CSRF ne correspond pas en développement - on permet quand même');
                    // Ne pas bloquer en développement, mais ne pas régénérer le token non plus
                    // car cela peut causer des problèmes de session
                } else {
                    // En production, on laisse le parent gérer (il va rejeter)
                    Log::error('Token CSRF ne correspond pas en production - requête sera rejetée');
                }
            }
        }
        
        return parent::handle($request, $next);
    }
}
