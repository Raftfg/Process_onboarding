<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\RateLimitService;
use Illuminate\Support\Facades\Log;

/**
 * Middleware de rate limiting pour les endpoints d'onboarding.
 * 
 * Applique des limites différentes selon l'endpoint :
 * - /start : 10 requêtes/heure par application
 * - /provision : 1 requête/24h par UUID
 * - /status : 100 requêtes/heure par application
 */
class RateLimitOnboarding
{
    public function __construct(
        protected RateLimitService $rateLimitService
    ) {
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $endpoint): Response
    {
        // Récupérer l'application depuis le middleware master.key (si disponible)
        $application = $request->get('application');
        
        // Récupérer l'UUID depuis la requête (pour provision)
        $uuid = $request->input('uuid') ?? $request->route('uuid');
        
        // Récupérer l'IP du client
        $ip = $request->ip();

        // Vérifier les limites
        $check = $this->rateLimitService->checkLimit($endpoint, $application, $uuid, $ip);

        if (!$check['allowed']) {
            // Récupérer les headers de rate limiting
            $headers = $this->rateLimitService->getHeaders($endpoint, $application, $uuid, $ip);
            
            Log::warning('Rate limit dépassé', [
                'endpoint' => $endpoint,
                'application_id' => $application?->id,
                'uuid' => $uuid,
                'ip' => $ip,
                'limit_type' => $check['limit_type'] ?? 'unknown',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Trop de requêtes. Veuillez réessayer plus tard.',
                'error' => 'rate_limit_exceeded',
                'retry_after_minutes' => $check['retry_after'],
            ], 429)
                ->withHeaders([
                    'X-RateLimit-Limit' => $headers['X-RateLimit-Limit'],
                    'X-RateLimit-Remaining' => 0,
                    'X-RateLimit-Reset' => $headers['X-RateLimit-Reset'],
                    'Retry-After' => $check['retry_after'] * 60, // en secondes
                ]);
        }

        // Ajouter les headers de rate limiting à la réponse
        $response = $next($request);
        
        $headers = $this->rateLimitService->getHeaders($endpoint, $application, $uuid, $ip);
        
        foreach ($headers as $key => $value) {
            $response->headers->set($key, $value);
        }

        return $response;
    }
}
