<?php

namespace App\Services;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;
use App\Models\Application;

/**
 * Service de gestion du rate limiting pour l'onboarding.
 * 
 * Gère les limites par endpoint, par application, par UUID et par IP.
 */
class RateLimitService
{
    /**
     * Limites configurées par endpoint
     */
    private const LIMITS = [
        'start' => [
            'max_attempts' => 10,
            'decay_minutes' => 60, // par heure
            'key_prefix' => 'onboarding:start',
        ],
        'provision' => [
            'max_attempts' => 1,
            'decay_minutes' => 1440, // 24 heures
            'key_prefix' => 'onboarding:provision',
        ],
        'status' => [
            'max_attempts' => 100,
            'decay_minutes' => 60, // par heure
            'key_prefix' => 'onboarding:status',
        ],
    ];

    /**
     * Limite globale par IP (tous endpoints confondus)
     */
    private const IP_LIMIT = [
        'max_attempts' => 50,
        'decay_minutes' => 60,
    ];

    /**
     * Vérifie si une requête dépasse les limites de rate limiting.
     * 
     * @param string $endpoint Le nom de l'endpoint (start, provision, status)
     * @param Application|null $application L'application cliente (si disponible)
     * @param string|null $uuid L'UUID du tenant (pour provision)
     * @param string $ip L'adresse IP du client
     * @return array ['allowed' => bool, 'remaining' => int, 'retry_after' => int|null]
     */
    public function checkLimit(
        string $endpoint,
        ?Application $application = null,
        ?string $uuid = null,
        string $ip = ''
    ): array {
        if (!isset(self::LIMITS[$endpoint])) {
            Log::warning("Endpoint de rate limiting inconnu: {$endpoint}");
            return ['allowed' => true, 'remaining' => 999, 'retry_after' => null];
        }

        $limit = self::LIMITS[$endpoint];
        
        // 1. Vérifier la limite par IP (globale)
        $ipKey = 'onboarding:ip:' . $ip;
        if (RateLimiter::tooManyAttempts($ipKey, self::IP_LIMIT['max_attempts'])) {
            $retryAfter = RateLimiter::availableIn($ipKey);
            return [
                'allowed' => false,
                'remaining' => 0,
                'retry_after' => (int) ceil($retryAfter / 60), // en minutes
                'limit_type' => 'ip',
            ];
        }

        // 2. Vérifier la limite spécifique à l'endpoint
        $key = $this->buildKey($endpoint, $application, $uuid, $ip);
        
        if (RateLimiter::tooManyAttempts($key, $limit['max_attempts'])) {
            $retryAfter = RateLimiter::availableIn($key);
            return [
                'allowed' => false,
                'remaining' => 0,
                'retry_after' => (int) ceil($retryAfter / 60), // en minutes
                'limit_type' => $endpoint,
            ];
        }

        // Compter la tentative
        RateLimiter::hit($key, $limit['decay_minutes'] * 60);
        RateLimiter::hit($ipKey, self::IP_LIMIT['decay_minutes'] * 60);

        $remaining = $limit['max_attempts'] - RateLimiter::attempts($key);

        return [
            'allowed' => true,
            'remaining' => max(0, $remaining),
            'retry_after' => null,
            'limit_type' => null,
        ];
    }

    /**
     * Construit la clé de rate limiting selon l'endpoint.
     */
    private function buildKey(
        string $endpoint,
        ?Application $application,
        ?string $uuid,
        string $ip
    ): string {
        $limit = self::LIMITS[$endpoint];
        $prefix = $limit['key_prefix'];

        switch ($endpoint) {
            case 'start':
                // Limite par application (X-Master-Key)
                if ($application) {
                    return "{$prefix}:app:{$application->id}";
                }
                // Fallback sur IP si pas d'application
                return "{$prefix}:ip:{$ip}";

            case 'provision':
                // Limite par UUID (tenant) - 1 par 24h
                if ($uuid) {
                    return "{$prefix}:uuid:{$uuid}";
                }
                // Fallback sur IP
                return "{$prefix}:ip:{$ip}";

            case 'status':
                // Limite par application
                if ($application) {
                    return "{$prefix}:app:{$application->id}";
                }
                // Fallback sur IP
                return "{$prefix}:ip:{$ip}";

            default:
                return "{$prefix}:ip:{$ip}";
        }
    }

    /**
     * Récupère les informations de rate limiting pour les headers de réponse.
     */
    public function getHeaders(string $endpoint, ?Application $application = null, ?string $uuid = null, string $ip = ''): array
    {
        $limit = self::LIMITS[$endpoint] ?? ['max_attempts' => 0, 'decay_minutes' => 60];
        $key = $this->buildKey($endpoint, $application, $uuid, $ip);
        
        $attempts = RateLimiter::attempts($key);
        $remaining = max(0, $limit['max_attempts'] - $attempts);
        
        return [
            'X-RateLimit-Limit' => $limit['max_attempts'],
            'X-RateLimit-Remaining' => $remaining,
            'X-RateLimit-Reset' => now()->addMinutes($limit['decay_minutes'])->timestamp,
        ];
    }
}
