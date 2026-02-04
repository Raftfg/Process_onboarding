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
        summary: "Enregistrer un nouveau webhook",
        tags: ["Webhooks"],
        security: [["ApiKey" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "url", type: "string", format: "url", example: "https://votre-app.com/webhooks"),
                    new OA\Property(
                        property: "events",
                        type: "array",
                        items: new OA\Items(type: "string", enum: ["onboarding.completed", "onboarding.failed", "test"])
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Webhook enregistré",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            properties: [
                                new OA\Property(property: "id", type: "integer"),
                                new OA\Property(property: "url", type: "string"),
                                new OA\Property(property: "secret", type: "string", description: "Secret pour vérifier les signatures HMAC")
                            ],
                            type: "object"
                        )
                    ]
                )
            )
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
