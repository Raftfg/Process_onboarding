<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class ApiKeyManagementController extends Controller
{
    #[OA\Get(
        path: "/api/v1/applications/{app_id}/api-keys",
        summary: "Lister les clés API",
        description: "Retourne la liste de toutes les clés API créées par l'application.",
        tags: ["Gestion des Clés API"],
        security: [
            ["MasterKey" => []]
        ],
        parameters: [
            new OA\Parameter(
                name: "app_id",
                in: "path",
                required: true,
                description: "ID de l'application",
                schema: new OA\Schema(type: "string", example: "app_abc123")
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des clés API",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(
                            property: "api_keys",
                            type: "array",
                            items: new OA\Items(
                                type: "object",
                                properties: [
                                    new OA\Property(property: "id", type: "integer", example: 1),
                                    new OA\Property(property: "name", type: "string", example: "Production Key"),
                                    new OA\Property(property: "key_prefix", type: "string", example: "ak_live_abc..."),
                                    new OA\Property(property: "app_name", type: "string", example: "mon-application"),
                                    new OA\Property(property: "is_active", type: "boolean", example: true),
                                    new OA\Property(property: "rate_limit", type: "integer", example: 100),
                                    new OA\Property(property: "expires_at", type: "string", format: "date-time", nullable: true),
                                    new OA\Property(property: "last_used_at", type: "string", format: "date-time", nullable: true),
                                    new OA\Property(property: "created_at", type: "string", format: "date-time"),
                                ]
                            )
                        ),
                        new OA\Property(property: "count", type: "integer", example: 3),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Master key invalide ou absente"),
        ]
    )]
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

    #[OA\Post(
        path: "/api/v1/applications/{app_id}/api-keys",
        summary: "Créer une nouvelle clé API",
        description: "Génère une nouvelle clé API avec un nom personnalisé, des limites de taux et une date d'expiration optionnelle.",
        tags: ["Gestion des Clés API"],
        security: [
            ["MasterKey" => []]
        ],
        parameters: [
            new OA\Parameter(
                name: "app_id",
                in: "path",
                required: true,
                description: "ID de l'application",
                schema: new OA\Schema(type: "string", example: "app_abc123")
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Production Key", description: "Nom de la clé API"),
                    new OA\Property(property: "rate_limit", type: "integer", nullable: true, example: 1000, description: "Limite de requêtes par minute (1-10000)"),
                    new OA\Property(property: "expires_at", type: "string", format: "date-time", nullable: true, example: "2026-12-31T23:59:59Z", description: "Date d'expiration (optionnel)"),
                    new OA\Property(property: "api_config", type: "object", nullable: true, description: "Configuration personnalisée (optionnel)"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Clé API créée avec succès",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Clé API créée avec succès"),
                        new OA\Property(
                            property: "api_key",
                            type: "object",
                            properties: [
                                new OA\Property(property: "id", type: "integer", example: 1),
                                new OA\Property(property: "key", type: "string", example: "ak_live_xyz789...", description: "⚠️ Affiché une seule fois"),
                                new OA\Property(property: "key_prefix", type: "string", example: "ak_live_abc..."),
                                new OA\Property(property: "name", type: "string", example: "Production Key"),
                                new OA\Property(property: "app_name", type: "string", example: "mon-application"),
                                new OA\Property(property: "rate_limit", type: "integer", example: 1000),
                                new OA\Property(property: "expires_at", type: "string", format: "date-time", nullable: true),
                            ]
                        ),
                        new OA\Property(property: "warning", type: "string", example: "⚠️ IMPORTANT: Sauvegardez cette clé immédiatement !"),
                    ]
                )
            ),
            new OA\Response(response: 403, description: "L'application ne peut pas créer de clés API"),
            new OA\Response(response: 422, description: "Erreur de validation"),
            new OA\Response(response: 401, description: "Master key invalide ou absente"),
        ]
    )]
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

    #[OA\Get(
        path: "/api/v1/applications/{app_id}/api-keys/{key_id}",
        summary: "Récupérer les détails d'une clé API",
        description: "Retourne les informations détaillées d'une clé API spécifique, incluant sa configuration.",
        tags: ["Gestion des Clés API"],
        security: [
            ["MasterKey" => []]
        ],
        parameters: [
            new OA\Parameter(
                name: "app_id",
                in: "path",
                required: true,
                description: "ID de l'application",
                schema: new OA\Schema(type: "string", example: "app_abc123")
            ),
            new OA\Parameter(
                name: "key_id",
                in: "path",
                required: true,
                description: "ID de la clé API",
                schema: new OA\Schema(type: "integer", example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Détails de la clé API",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(
                            property: "api_key",
                            type: "object",
                            properties: [
                                new OA\Property(property: "id", type: "integer", example: 1),
                                new OA\Property(property: "name", type: "string", example: "Production Key"),
                                new OA\Property(property: "key_prefix", type: "string", example: "ak_live_abc..."),
                                new OA\Property(property: "app_name", type: "string", example: "mon-application"),
                                new OA\Property(property: "is_active", type: "boolean", example: true),
                                new OA\Property(property: "rate_limit", type: "integer", example: 1000),
                                new OA\Property(property: "expires_at", type: "string", format: "date-time", nullable: true),
                                new OA\Property(property: "last_used_at", type: "string", format: "date-time", nullable: true),
                                new OA\Property(property: "created_at", type: "string", format: "date-time"),
                                new OA\Property(property: "api_config", type: "object", description: "Configuration de la clé"),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Clé API introuvable"),
            new OA\Response(response: 401, description: "Master key invalide ou absente"),
        ]
    )]
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

    #[OA\Put(
        path: "/api/v1/applications/{app_id}/api-keys/{key_id}/config",
        summary: "Configurer une clé API",
        description: "Met à jour la configuration d'une clé API (validation, stratégie de génération de noms, etc.).",
        tags: ["Gestion des Clés API"],
        security: [
            ["MasterKey" => []]
        ],
        parameters: [
            new OA\Parameter(
                name: "app_id",
                in: "path",
                required: true,
                description: "ID de l'application",
                schema: new OA\Schema(type: "string", example: "app_abc123")
            ),
            new OA\Parameter(
                name: "key_id",
                in: "path",
                required: true,
                description: "ID de la clé API",
                schema: new OA\Schema(type: "integer", example: 1)
            ),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "require_organization_name", type: "boolean", nullable: true, example: false, description: "Exiger le nom d'organisation"),
                    new OA\Property(property: "organization_name_generation_strategy", type: "string", nullable: true, enum: ["auto", "email", "timestamp", "metadata", "custom"], example: "email", description: "Stratégie de génération du nom"),
                    new OA\Property(property: "organization_name_template", type: "string", nullable: true, example: "Org-{email}", description: "Template personnalisé (si stratégie = custom)"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Configuration mise à jour",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Configuration mise à jour avec succès"),
                        new OA\Property(property: "api_config", type: "object", description: "Nouvelle configuration"),
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Clé API introuvable"),
            new OA\Response(response: 422, description: "Erreur de validation"),
            new OA\Response(response: 401, description: "Master key invalide ou absente"),
        ]
    )]
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

    #[OA\Delete(
        path: "/api/v1/applications/{app_id}/api-keys/{key_id}",
        summary: "Révoquer une clé API",
        description: "Désactive une clé API. La clé devient immédiatement inutilisable. Utile pour la sécurité en cas de compromission.",
        tags: ["Gestion des Clés API"],
        security: [
            ["MasterKey" => []]
        ],
        parameters: [
            new OA\Parameter(
                name: "app_id",
                in: "path",
                required: true,
                description: "ID de l'application",
                schema: new OA\Schema(type: "string", example: "app_abc123")
            ),
            new OA\Parameter(
                name: "key_id",
                in: "path",
                required: true,
                description: "ID de la clé API à révoquer",
                schema: new OA\Schema(type: "integer", example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Clé API révoquée avec succès",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Clé API révoquée avec succès"),
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Clé API introuvable"),
            new OA\Response(response: 401, description: "Master key invalide ou absente"),
        ]
    )]
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
