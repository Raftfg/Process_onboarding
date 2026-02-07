<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class WebhookController extends Controller
{
    protected $webhookService;

    public function __construct(WebhookService $webhookService)
    {
        $this->webhookService = $webhookService;
    }

    #[OA\Post(
        path: "/api/webhooks/register",
        summary: "Enregistrer un webhook",
        description: "Enregistre une URL qui recevra des notifications automatiques lors d'événements d'onboarding.",
        tags: ["Webhooks"],
        security: [
            ["ApiKey" => []]
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["url", "events"],
                properties: [
                    new OA\Property(property: "url", type: "string", format: "uri", example: "https://monapp.com/webhooks/onboarding", description: "URL qui recevra les notifications"),
                    new OA\Property(
                        property: "events",
                        type: "array",
                        items: new OA\Items(type: "string", enum: ["onboarding.completed", "onboarding.failed", "test"]),
                        example: ["onboarding.completed", "onboarding.failed"],
                        description: "Événements à écouter"
                    ),
                    new OA\Property(property: "api_key_id", type: "integer", nullable: true, example: 1, description: "ID de la clé API associée (optionnel)"),
                    new OA\Property(property: "timeout", type: "integer", nullable: true, example: 30, description: "Timeout en secondes (5-120, défaut: 30)"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Webhook enregistré avec succès",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "id", type: "integer", example: 1),
                                new OA\Property(property: "url", type: "string", format: "uri"),
                                new OA\Property(property: "events", type: "array", items: new OA\Items(type: "string")),
                                new OA\Property(property: "secret", type: "string", example: "secret_xyz789...", description: "⚠️ Secret pour vérifier les signatures, à sauvegarder"),
                                new OA\Property(property: "created_at", type: "string", format: "date-time"),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Erreur de validation"),
            new OA\Response(response: 401, description: "Clé API invalide ou absente"),
        ]
    )]
    /**
     * Enregistrer un nouveau webhook
     * 
     * POST /api/webhooks/register
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'url' => 'required|url|max:500',
            'events' => 'required|array',
            'events.*' => 'string|in:onboarding.completed,onboarding.failed,test',
            'api_key_id' => 'nullable|exists:api_keys,id',
            'timeout' => 'nullable|integer|min:5|max:120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $webhook = $this->webhookService->create([
                'url' => $request->input('url'),
                'events' => $request->input('events'),
                'api_key_id' => $request->input('api_key_id'),
                'timeout' => $request->input('timeout', 30),
                'secret' => Str::random(32),
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $webhook->id,
                    'url' => $webhook->url,
                    'events' => $webhook->events,
                    'secret' => $webhook->secret, // À sauvegarder pour vérifier les signatures
                    'created_at' => $webhook->created_at->toIso8601String(),
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du webhook: ' . $e->getMessage()
            ], 500);
        }
    }

    #[OA\Get(
        path: "/api/webhooks",
        summary: "Lister les webhooks",
        description: "Retourne la liste de tous les webhooks enregistrés. Peut être filtré par api_key_id.",
        tags: ["Webhooks"],
        security: [
            ["ApiKey" => []]
        ],
        parameters: [
            new OA\Parameter(
                name: "api_key_id",
                in: "query",
                required: false,
                description: "Filtrer par ID de clé API",
                schema: new OA\Schema(type: "integer", example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des webhooks",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(
                                type: "object",
                                properties: [
                                    new OA\Property(property: "id", type: "integer", example: 1),
                                    new OA\Property(property: "url", type: "string", format: "uri"),
                                    new OA\Property(property: "events", type: "array", items: new OA\Items(type: "string")),
                                    new OA\Property(property: "is_active", type: "boolean", example: true),
                                    new OA\Property(property: "last_triggered_at", type: "string", format: "date-time", nullable: true),
                                    new OA\Property(property: "created_at", type: "string", format: "date-time"),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Clé API invalide ou absente"),
        ]
    )]
    /**
     * Lister les webhooks
     * 
     * GET /api/webhooks
     */
    public function index(Request $request)
    {
        $webhooks = \App\Models\Webhook::query();

        if ($request->has('api_key_id')) {
            $webhooks->where('api_key_id', $request->input('api_key_id'));
        }

        $webhooks = $webhooks->get();

        return response()->json([
            'success' => true,
            'data' => $webhooks->map(function ($webhook) {
                return [
                    'id' => $webhook->id,
                    'url' => $webhook->url,
                    'events' => $webhook->events,
                    'is_active' => $webhook->is_active,
                    'last_triggered_at' => $webhook->last_triggered_at?->toIso8601String(),
                    'created_at' => $webhook->created_at->toIso8601String(),
                ];
            })
        ]);
    }

    #[OA\Delete(
        path: "/api/webhooks/{id}",
        summary: "Désactiver un webhook",
        description: "Désactive un webhook. Il ne recevra plus de notifications.",
        tags: ["Webhooks"],
        security: [
            ["ApiKey" => []]
        ],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID du webhook",
                schema: new OA\Schema(type: "integer", example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Webhook désactivé avec succès",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Webhook désactivé avec succès"),
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Webhook non trouvé"),
            new OA\Response(response: 401, description: "Clé API invalide ou absente"),
        ]
    )]
    /**
     * Désactiver un webhook
     * 
     * DELETE /api/webhooks/{id}
     */
    public function destroy($id)
    {
        $webhook = \App\Models\Webhook::find($id);

        if (!$webhook) {
            return response()->json([
                'success' => false,
                'message' => 'Webhook non trouvé'
            ], 404);
        }

        $webhook->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Webhook désactivé avec succès'
        ]);
    }

    #[OA\Post(
        path: "/api/webhooks/test",
        summary: "Tester les webhooks",
        description: "Déclenche un événement de test vers tous les webhooks actifs. Utile pour vérifier que votre endpoint reçoit bien les notifications.",
        tags: ["Webhooks"],
        security: [
            ["ApiKey" => []]
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Webhooks de test déclenchés",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Webhooks de test déclenchés"),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Clé API invalide ou absente"),
        ]
    )]
    /**
     * Déclencher un webhook de test
     * 
     * POST /api/webhooks/test
     */
    public function triggerTest(Request $request)
    {
        $this->webhookService->trigger('test', [
            'message' => 'Ceci est un webhook de test pour Akasi Group Microservice',
            'test_id' => Str::random(10),
            'timestamp' => now()->toIso8601String()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Webhooks de test déclenchés'
        ]);
    }
}
