<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ApiKeyManagementController extends Controller
{
    /**
     * Liste toutes les clés API d'une application
     * 
     * GET /api/v1/applications/{app_id}/api-keys
     */
    public function index(Request $request, string $appId)
    {
        $application = $request->get('application');

        $apiKeys = $application->apiKeys()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($key) {
                return [
                    'id' => $key->id,
                    'name' => $key->name,
                    'key_prefix' => $key->key_prefix,
                    'app_name' => $key->app_name,
                    'is_active' => $key->is_active,
                    'rate_limit' => $key->rate_limit,
                    'expires_at' => $key->expires_at?->toIso8601String(),
                    'last_used_at' => $key->last_used_at?->toIso8601String(),
                    'created_at' => $key->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'success' => true,
            'api_keys' => $apiKeys,
            'count' => $apiKeys->count(),
        ]);
    }

    /**
     * Crée une nouvelle clé API pour une application
     * 
     * POST /api/v1/applications/{app_id}/api-keys
     */
    public function store(Request $request, string $appId)
    {
        $application = $request->get('application');

        // Vérifier que l'application peut créer des clés
        if (!$application->canCreateApiKeys()) {
            return response()->json([
                'success' => false,
                'message' => 'Votre application ne peut pas créer de clés API. Contactez le support.'
            ], 403);
        }

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'rate_limit' => 'nullable|integer|min:1|max:10000',
                'expires_at' => 'nullable|date|after:now',
                'api_config' => 'nullable|array',
            ]);

            // Générer la clé API
            $result = ApiKey::generate($validated['name'], [
                'app_name' => $application->app_name,
                'application_id' => $application->id,
                'rate_limit' => $validated['rate_limit'] ?? 100,
                'expires_at' => $validated['expires_at'] ?? null,
                'api_config' => $validated['api_config'] ?? null,
            ]);

            Log::info('Nouvelle clé API créée via self-service', [
                'app_id' => $application->app_id,
                'app_name' => $application->app_name,
                'key_id' => $result['id'],
                'key_prefix' => $result['key_prefix'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Clé API créée avec succès',
                'api_key' => [
                    'id' => $result['id'],
                    'key' => $result['key'],
                    'key_prefix' => $result['key_prefix'],
                    'name' => $result['name'],
                    'app_name' => $result['app_name'],
                    'rate_limit' => $validated['rate_limit'] ?? 100,
                    'expires_at' => $validated['expires_at'] ?? null,
                ],
                'warning' => '⚠️ IMPORTANT: Sauvegardez cette clé immédiatement ! Elle ne sera plus jamais affichée.',
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la création de clé API: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la création de la clé API.'
            ], 500);
        }
    }

    /**
     * Récupère les détails d'une clé API
     * 
     * GET /api/v1/applications/{app_id}/api-keys/{key_id}
     */
    public function show(Request $request, string $appId, int $keyId)
    {
        $application = $request->get('application');

        $apiKey = $application->apiKeys()->find($keyId);

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'Clé API introuvable ou vous n\'avez pas l\'autorisation d\'y accéder.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'api_key' => [
                'id' => $apiKey->id,
                'name' => $apiKey->name,
                'key_prefix' => $apiKey->key_prefix,
                'app_name' => $apiKey->app_name,
                'is_active' => $apiKey->is_active,
                'rate_limit' => $apiKey->rate_limit,
                'expires_at' => $apiKey->expires_at?->toIso8601String(),
                'last_used_at' => $apiKey->last_used_at?->toIso8601String(),
                'created_at' => $apiKey->created_at->toIso8601String(),
                'api_config' => $apiKey->api_config ?? ApiKey::getDefaultApiConfig(),
            ],
        ]);
    }

    /**
     * Met à jour la configuration d'une clé API
     * 
     * PUT /api/v1/applications/{app_id}/api-keys/{key_id}/config
     */
    public function updateConfig(Request $request, string $appId, int $keyId)
    {
        $application = $request->get('application');

        $apiKey = $application->apiKeys()->find($keyId);

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'Clé API introuvable ou vous n\'avez pas l\'autorisation d\'y accéder.'
            ], 404);
        }

        try {
            $validated = $request->validate([
                'require_organization_name' => 'nullable|boolean',
                'organization_name_generation_strategy' => 'nullable|in:auto,email,timestamp,metadata,custom',
                'organization_name_template' => 'nullable|string|max:255',
            ]);

            $currentConfig = $apiKey->api_config ?? ApiKey::getDefaultApiConfig();

            $newConfig = [
                'require_organization_name' => $request->has('require_organization_name') 
                    ? (bool) $request->require_organization_name 
                    : ($currentConfig['require_organization_name'] ?? true),
                'organization_name_generation_strategy' => $validated['organization_name_generation_strategy'] 
                    ?? $currentConfig['organization_name_generation_strategy'] 
                    ?? 'auto',
                'organization_name_template' => $validated['organization_name_template'] 
                    ?? $currentConfig['organization_name_template'] 
                    ?? null,
                'custom_validation_rules' => $currentConfig['custom_validation_rules'] ?? [],
            ];

            // Si la stratégie n'est pas "custom", supprimer le template
            if ($newConfig['organization_name_generation_strategy'] !== 'custom') {
                $newConfig['organization_name_template'] = null;
            }

            $apiKey->update(['api_config' => $newConfig]);

            Log::info('Configuration de clé API mise à jour via self-service', [
                'app_id' => $application->app_id,
                'key_id' => $apiKey->id,
                'config' => $newConfig,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Configuration mise à jour avec succès',
                'api_config' => $newConfig,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise à jour de config: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la mise à jour.'
            ], 500);
        }
    }

    /**
     * Révoque (désactive) une clé API
     * 
     * DELETE /api/v1/applications/{app_id}/api-keys/{key_id}
     */
    public function destroy(Request $request, string $appId, int $keyId)
    {
        $application = $request->get('application');

        $apiKey = $application->apiKeys()->find($keyId);

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'Clé API introuvable ou vous n\'avez pas l\'autorisation d\'y accéder.'
            ], 404);
        }

        // Désactiver plutôt que supprimer (pour l'audit)
        $apiKey->update(['is_active' => false]);

        Log::info('Clé API révoquée via self-service', [
            'app_id' => $application->app_id,
            'key_id' => $apiKey->id,
            'key_prefix' => $apiKey->key_prefix,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Clé API révoquée avec succès',
        ]);
    }
}
