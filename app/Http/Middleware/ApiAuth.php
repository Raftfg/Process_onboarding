<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class ApiAuth
{
    /**
     * Handle an incoming request.
     * 
     * Vérifie que la requête contient une clé API valide
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Essayer de récupérer la clé depuis le header X-API-Key (prioritaire selon doc)
        $apiKey = $request->header('X-API-Key');

        // 2. Sinon, essayer depuis le header Authorization
        if (!$apiKey) {
            $authHeader = $request->header('Authorization');
            if ($authHeader) {
                if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                    $apiKey = trim($matches[1]);
                } else {
                    $apiKey = trim($authHeader);
                }
            }
        }

        // Si aucune clé n'est trouvée
        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'Clé API manquante. Veuillez fournir une clé API via le header X-API-Key ou Authorization.'
            ], 401);
        }

        // Vérifier la clé API
        if (!$this->validateApiKey($request, $apiKey)) {
            Log::warning('Tentative d\'accès avec une clé API invalide', [
                'ip' => $request->ip(),
                'key_prefix' => substr($apiKey, 0, 8) . '...'
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Clé API invalide ou non autorisée pour cette application.'
            ], 401);
        }

        // Ajouter la clé API à la requête pour utilisation ultérieure
        $request->merge(['api_key' => $apiKey]);

        return $next($request);
    }

    /**
     * Valide la clé API
     * 
     * Vérifie d'abord en base de données, puis en variable d'environnement
     */
    protected function validateApiKey(Request $request, string $apiKey): bool
    {
        // Option 1: Vérifier en base de données (recommandé)
        if (class_exists(\App\Models\ApiKey::class)) {
            $apiKeyModel = \App\Models\ApiKey::validate($apiKey);
            
            if ($apiKeyModel) {
                // 1. Vérifier l'IP si restreinte
                if (!$apiKeyModel->isIpAllowed($request->ip())) {
                    return false;
                }

                // 2. Vérifier le Nom de l'Application (Binding)
                // Si la clé est liée à une application spécifique, le header X-App-Name DOIT correspondre
                if (!empty($apiKeyModel->app_name)) {
                    $requestAppName = $request->header('X-App-Name') ?? $request->header('X-Source-App');
                    
                    if (!$requestAppName || $requestAppName !== $apiKeyModel->app_name) {
                        Log::warning('Rejet API: App Name incorrect', [
                            'key_app' => $apiKeyModel->app_name,
                            'request_app' => $requestAppName,
                            'ip' => $request->ip()
                        ]);
                        return false;
                    }
                }

                return true;
            }
        }

        // Option 2: Vérifier contre une variable d'environnement (fallback)
        $validApiKey = env('API_KEY');
        
        if ($validApiKey && hash_equals($validApiKey, $apiKey)) {
            return true;
        }

        return false;
    }
}
