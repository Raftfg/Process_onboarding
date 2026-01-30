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
        // Récupérer la clé API depuis le header Authorization
        $authHeader = $request->header('Authorization');
        
        if (!$authHeader) {
            return response()->json([
                'success' => false,
                'message' => 'Clé API manquante. Veuillez fournir une clé API dans le header Authorization.'
            ], 401);
        }

        // Extraire la clé API (format: "Bearer YOUR_API_KEY")
        $apiKey = null;
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $apiKey = trim($matches[1]);
        } else {
            // Accepter aussi directement la clé sans "Bearer"
            $apiKey = trim($authHeader);
        }

        if (empty($apiKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Format de clé API invalide. Utilisez: Authorization: Bearer YOUR_API_KEY'
            ], 401);
        }

        // Vérifier la clé API
        if (!$this->validateApiKey($apiKey)) {
            Log::warning('Tentative d\'accès avec une clé API invalide', [
                'ip' => $request->ip(),
                'key_prefix' => substr($apiKey, 0, 8) . '...'
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Clé API invalide ou expirée.'
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
    protected function validateApiKey(string $apiKey): bool
    {
        // Option 1: Vérifier en base de données (recommandé)
        if (class_exists(\App\Models\ApiKey::class)) {
            $apiKeyModel = \App\Models\ApiKey::validate($apiKey);
            
            if ($apiKeyModel) {
                // Vérifier l'IP si restreinte
                $request = request();
                if (!$apiKeyModel->isIpAllowed($request->ip())) {
                    return false;
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
