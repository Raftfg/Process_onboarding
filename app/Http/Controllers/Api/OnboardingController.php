<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OnboardingOrchestratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

/**
 * Contrôleur REST stateless pour l'orchestration d'onboarding.
 *
 * Endpoints :
 * - POST /api/v1/onboarding/start
 * - POST /api/v1/onboarding/provision
 * - GET  /api/v1/onboarding/status/{uuid}
 *
 * Sécurisé par middleware master.key (X-Master-Key).
 * Ne crée pas de tenant, ne gère pas de sessions, ne fait pas d'emails.
 */
#[OA\Tag(
    name: "Onboarding Stateless",
    description: "Flux d'onboarding stateless basé sur uuid + sous-domaine. L'application cliente reste propriétaire du tenant métier."
)]
class OnboardingController extends Controller
{
    public function __construct(
        protected OnboardingOrchestratorService $onboardingOrchestratorService
    ) {
    }

    #[OA\Post(
        path: "/api/v1/onboarding/start",
        summary: "Démarrer un onboarding stateless",
        description: "Crée un enregistrement d'onboarding dans la base centrale et génère un sous-domaine.",
        tags: ["Onboarding Stateless"],
        security: [
            ["MasterKey" => []]
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", example: "admin@example.com"),
                    new OA\Property(property: "organization_name", type: "string", nullable: true, example: "Clinique du Lac"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Onboarding créé",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "uuid", type: "string", format: "uuid"),
                        new OA\Property(property: "subdomain", type: "string", example: "clinique-du-lac"),
                        new OA\Property(property: "full_domain", type: "string", example: "clinique-du-lac.akasigroup.local"),
                        new OA\Property(property: "url", type: "string", format: "uri", example: "https://clinique-du-lac.akasigroup.local"),
                        new OA\Property(property: "email", type: "string", format: "email"),
                        new OA\Property(property: "organization_name", type: "string", nullable: true),
                        new OA\Property(property: "onboarding_status", type: "string", example: "pending"),
                        new OA\Property(
                            property: "metadata",
                            type: "object",
                            properties: [
                                new OA\Property(property: "created_at", type: "string", format: "date-time"),
                                new OA\Property(property: "updated_at", type: "string", format: "date-time"),
                                new OA\Property(property: "dns_configured", type: "boolean", example: false),
                                new OA\Property(property: "ssl_configured", type: "boolean", example: false),
                                new OA\Property(property: "infrastructure_status", type: "string", example: "pending"),
                                new OA\Property(property: "api_key_generated", type: "boolean", example: false),
                                new OA\Property(property: "provisioning_attempts", type: "integer", example: 0),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 422, description: "Erreur de validation"),
            new OA\Response(response: 401, description: "Master key invalide ou absente"),
            new OA\Response(response: 500, description: "Erreur interne lors du démarrage de l'onboarding"),
        ]
    )]
    public function start(Request $request)
    {
        try {
            $application = $request->get('application');

            if (!$application) {
                return response()->json([
                    'success' => false,
                    'message' => 'Application introuvable. Vérifiez votre X-Master-Key.',
                ], 401);
            }

            $validated = $request->validate([
                'email'             => 'required|email|max:255',
                'organization_name' => 'nullable|string|max:255',
            ]);

            $registration = $this->onboardingOrchestratorService->start(
                $application,
                $validated['email'],
                $validated['organization_name'] ?? null
            );

            // Construire les metadata enrichies
            $metadata = [
                'created_at' => $registration->created_at->toIso8601String(),
                'updated_at' => $registration->updated_at->toIso8601String(),
                'dns_configured' => $registration->dns_configured,
                'ssl_configured' => $registration->ssl_configured,
                'infrastructure_status' => $this->getInfrastructureStatus($registration),
                'api_key_generated' => !empty($registration->api_key),
                'provisioning_attempts' => $registration->provisioning_attempts ?? 0,
            ];

            // Générer l'URL complète du sous-domaine pour l'application cliente
            $subdomainService = app(\App\Services\SubdomainService::class);
            $fullUrl = $subdomainService->getSubdomainUrl($registration->subdomain);

            return response()->json([
                'success'           => true,
                'uuid'              => $registration->uuid,
                'subdomain'         => $registration->subdomain,
                'full_domain'       => $registration->subdomain . '.' . config('app.brand_domain', 'akasigroup.local'),
                'url'               => $fullUrl,
                'email'             => $registration->email,
                'organization_name' => $registration->organization_name,
                'onboarding_status' => $registration->status,
                'metadata'          => $metadata,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Erreur /onboarding/start', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors du démarrage de l\'onboarding.',
            ], 500);
        }
    }

    #[OA\Post(
        path: "/api/v1/onboarding/provision",
        summary: "Provisionner l'infrastructure d'un onboarding",
        description: "Configure DNS/SSL pour le sous-domaine et génère éventuellement une clé API.",
        tags: ["Onboarding Stateless"],
        security: [
            ["MasterKey" => []]
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["uuid"],
                properties: [
                    new OA\Property(property: "uuid", type: "string", format: "uuid"),
                    new OA\Property(property: "generate_api_key", type: "boolean", nullable: true, example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Onboarding provisionné",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "uuid", type: "string", format: "uuid"),
                        new OA\Property(property: "subdomain", type: "string", example: "clinique-du-lac"),
                        new OA\Property(property: "full_domain", type: "string", example: "clinique-du-lac.akasigroup.local"),
                        new OA\Property(property: "url", type: "string", format: "uri", example: "https://clinique-du-lac.akasigroup.local"),
                        new OA\Property(property: "email", type: "string", format: "email"),
                        new OA\Property(property: "organization_name", type: "string", nullable: true),
                        new OA\Property(property: "onboarding_status", type: "string", example: "activated"),
                        new OA\Property(property: "api_key", type: "string", nullable: true, example: "ak_abc123..."),
                        new OA\Property(property: "api_secret", type: "string", nullable: true, example: "ak_abc123..."),
                        new OA\Property(
                            property: "metadata",
                            type: "object",
                            properties: [
                                new OA\Property(property: "created_at", type: "string", format: "date-time"),
                                new OA\Property(property: "updated_at", type: "string", format: "date-time"),
                                new OA\Property(property: "dns_configured", type: "boolean", example: true),
                                new OA\Property(property: "ssl_configured", type: "boolean", example: true),
                                new OA\Property(property: "infrastructure_status", type: "string", example: "ready"),
                                new OA\Property(property: "api_key_generated", type: "boolean", example: true),
                                new OA\Property(property: "provisioning_attempts", type: "integer", example: 1),
                                new OA\Property(property: "is_idempotent", type: "boolean", example: false),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Onboarding introuvable pour cette application"),
            new OA\Response(response: 422, description: "Erreur de validation"),
            new OA\Response(response: 401, description: "Master key invalide ou absente"),
            new OA\Response(response: 500, description: "Erreur interne lors du provisioning"),
        ]
    )]
    public function provision(Request $request)
    {
        try {
            $application = $request->get('application');

            if (!$application) {
                return response()->json([
                    'success' => false,
                    'message' => 'Application introuvable. Vérifiez votre X-Master-Key.',
                ], 401);
            }

            $validated = $request->validate([
                'uuid'            => 'required|string',
                'generate_api_key'=> 'nullable|boolean',
            ]);

            $result = $this->onboardingOrchestratorService->provision(
                $application,
                $validated['uuid'],
                (bool) ($validated['generate_api_key'] ?? false)
            );

            $registration     = $result['registration'];
            $apiKeyPlain      = $result['api_key_plain'];
            $apiSecretPlain   = $result['api_secret_plain'];
            $isIdempotent     = $result['is_idempotent'] ?? false;

            // Construire les metadata enrichies
            $metadata = [
                'created_at' => $registration->created_at->toIso8601String(),
                'updated_at' => $registration->updated_at->toIso8601String(),
                'dns_configured' => $registration->dns_configured,
                'ssl_configured' => $registration->ssl_configured,
                'infrastructure_status' => $this->getInfrastructureStatus($registration),
                'api_key_generated' => !empty($apiKeyPlain) || !empty($registration->api_key),
                'provisioning_attempts' => $registration->provisioning_attempts ?? 0,
                'is_idempotent' => $isIdempotent,
            ];

            // Générer l'URL complète du sous-domaine pour l'application cliente
            $subdomainService = app(\App\Services\SubdomainService::class);
            $fullUrl = $subdomainService->getSubdomainUrl($registration->subdomain);

            return response()->json([
                'success'           => true,
                'uuid'              => $registration->uuid,
                'subdomain'         => $registration->subdomain,
                'full_domain'       => $registration->subdomain . '.' . config('app.brand_domain', 'akasigroup.local'),
                'url'               => $fullUrl,
                'email'             => $registration->email,
                'organization_name' => $registration->organization_name,
                'onboarding_status' => $registration->status,
                'api_key'           => $apiKeyPlain,
                'api_secret'        => $apiSecretPlain,
                'metadata'          => $metadata,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\RuntimeException $e) {
            // Cas fonctionnels (uuid inexistant, etc.)
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        } catch (\Throwable $e) {
            Log::error('Erreur /onboarding/provision', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors du provisioning de l\'onboarding.',
            ], 500);
        }
    }

    #[OA\Get(
        path: "/api/v1/onboarding/status/{uuid}",
        summary: "Récupérer le statut d'un onboarding",
        description: "Retourne le statut technique (pending/activated/cancelled) et les informations de sous-domaine.",
        tags: ["Onboarding Stateless"],
        security: [
            ["MasterKey" => []]
        ],
        parameters: [
            new OA\Parameter(
                name: "uuid",
                in: "path",
                required: true,
                description: "UUID retourné par /onboarding/start",
                schema: new OA\Schema(type: "string", format: "uuid")
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Statut de l'onboarding",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "uuid", type: "string", format: "uuid"),
                        new OA\Property(property: "subdomain", type: "string", example: "clinique-du-lac"),
                        new OA\Property(property: "full_domain", type: "string", example: "clinique-du-lac.akasigroup.local"),
                        new OA\Property(property: "url", type: "string", format: "uri", example: "https://clinique-du-lac.akasigroup.local"),
                        new OA\Property(property: "email", type: "string", format: "email"),
                        new OA\Property(property: "organization_name", type: "string", nullable: true),
                        new OA\Property(property: "onboarding_status", type: "string", example: "activated"),
                        new OA\Property(property: "dns_configured", type: "boolean", example: true),
                        new OA\Property(property: "ssl_configured", type: "boolean", example: true),
                        new OA\Property(
                            property: "metadata",
                            type: "object",
                            properties: [
                                new OA\Property(property: "created_at", type: "string", format: "date-time"),
                                new OA\Property(property: "updated_at", type: "string", format: "date-time"),
                                new OA\Property(property: "dns_configured", type: "boolean", example: true),
                                new OA\Property(property: "ssl_configured", type: "boolean", example: true),
                                new OA\Property(property: "infrastructure_status", type: "string", example: "ready"),
                                new OA\Property(property: "api_key_generated", type: "boolean", example: true),
                                new OA\Property(property: "provisioning_attempts", type: "integer", example: 1),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Onboarding introuvable pour cette application"),
            new OA\Response(response: 401, description: "Master key invalide ou absente"),
            new OA\Response(response: 500, description: "Erreur interne lors de la récupération du statut"),
        ]
    )]
    public function status(Request $request, string $uuid)
    {
        try {
            $application = $request->get('application');

            if (!$application) {
                return response()->json([
                    'success' => false,
                    'message' => 'Application introuvable. Vérifiez votre X-Master-Key.',
                ], 401);
            }

            $registration = $this->onboardingOrchestratorService->getStatus($application, $uuid);

            if (!$registration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Onboarding introuvable.',
                ], 404);
            }

            // Construire les metadata enrichies
            $metadata = [
                'created_at' => $registration->created_at->toIso8601String(),
                'updated_at' => $registration->updated_at->toIso8601String(),
                'dns_configured' => $registration->dns_configured,
                'ssl_configured' => $registration->ssl_configured,
                'infrastructure_status' => $this->getInfrastructureStatus($registration),
                'api_key_generated' => !empty($registration->api_key),
                'provisioning_attempts' => $registration->provisioning_attempts ?? 0,
            ];

            // Générer l'URL complète du sous-domaine pour l'application cliente
            $subdomainService = app(\App\Services\SubdomainService::class);
            $fullUrl = $subdomainService->getSubdomainUrl($registration->subdomain);

            return response()->json([
                'success'           => true,
                'uuid'              => $registration->uuid,
                'subdomain'         => $registration->subdomain,
                'full_domain'       => $registration->subdomain . '.' . config('app.brand_domain', 'akasigroup.local'),
                'url'               => $fullUrl,
                'email'             => $registration->email,
                'organization_name' => $registration->organization_name,
                'onboarding_status' => $registration->status,
                'dns_configured'    => $registration->dns_configured,
                'ssl_configured'    => $registration->ssl_configured,
                'metadata'          => $metadata,
            ]);
        } catch (\Throwable $e) {
            Log::error('Erreur /onboarding/status', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la récupération du statut.',
            ], 500);
        }
    }

    /**
     * Marque un onboarding comme complété par l'application cliente
     * 
     * POST /api/v1/onboarding/{uuid}/complete
     */
    #[OA\Post(
        path: "/api/v1/onboarding/{uuid}/complete",
        summary: "Marquer un onboarding comme complété",
        description: "Permet à l'application cliente de signaler qu'elle a terminé la création du tenant et que l'onboarding est complété.",
        tags: ["Onboarding Stateless"],
        security: [
            ["MasterKey" => []]
        ],
        parameters: [
            new OA\Parameter(
                name: "uuid",
                in: "path",
                required: true,
                description: "UUID retourné par /onboarding/start",
                schema: new OA\Schema(type: "string", format: "uuid")
            ),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "tenant_id", type: "string", nullable: true, example: "tenant_123", description: "ID du tenant créé côté application cliente"),
                    new OA\Property(property: "metadata", type: "object", nullable: true, description: "Métadonnées supplémentaires sur le tenant créé"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Onboarding marqué comme complété",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Onboarding marqué comme complété"),
                        new OA\Property(property: "uuid", type: "string", format: "uuid"),
                        new OA\Property(property: "onboarding_status", type: "string", example: "completed"),
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Onboarding introuvable pour cette application"),
            new OA\Response(response: 401, description: "Master key invalide ou absente"),
            new OA\Response(response: 422, description: "Onboarding déjà complété ou statut invalide"),
        ]
    )]
    public function complete(Request $request, string $uuid)
    {
        try {
            $application = $request->get('application');

            if (!$application) {
                return response()->json([
                    'success' => false,
                    'message' => 'Application introuvable. Vérifiez votre X-Master-Key.',
                ], 401);
            }

            $registration = $this->onboardingOrchestratorService->getStatus($application, $uuid);

            if (!$registration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Onboarding introuvable.',
                ], 404);
            }

            // Vérifier que l'onboarding peut être complété
            if ($registration->status === 'completed') {
                return response()->json([
                    'success' => true,
                    'message' => 'Onboarding déjà complété',
                    'uuid' => $registration->uuid,
                    'onboarding_status' => 'completed',
                ]);
            }

            if ($registration->status === 'cancelled') {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de compléter un onboarding annulé.',
                ], 422);
            }

            // Valider les données optionnelles
            $validated = $request->validate([
                'tenant_id' => 'nullable|string|max:255',
                'metadata' => 'nullable|array',
            ]);

            // Mettre à jour le statut
            $registration->status = 'completed';
            $registration->completed_at = now();
            
            // Stocker les métadonnées du tenant si fournies
            if (!empty($validated['tenant_id']) || !empty($validated['metadata'])) {
                $currentMetadata = $registration->metadata ?? [];
                $registration->metadata = array_merge($currentMetadata, [
                    'tenant_id' => $validated['tenant_id'] ?? null,
                    'client_metadata' => $validated['metadata'] ?? [],
                    'completed_at' => now()->toIso8601String(),
                ]);
            }
            
            $registration->save();

            Log::info('Onboarding marqué comme complété par l\'application cliente', [
                'application_id' => $application->id,
                'uuid' => $registration->uuid,
                'subdomain' => $registration->subdomain,
                'tenant_id' => $validated['tenant_id'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Onboarding marqué comme complété avec succès',
                'uuid' => $registration->uuid,
                'onboarding_status' => 'completed',
                'completed_at' => $registration->completed_at->toIso8601String(),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Erreur /onboarding/complete', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la finalisation de l\'onboarding.',
            ], 500);
        }
    }

    /**
     * Détermine le statut de l'infrastructure
     */
    private function getInfrastructureStatus($registration): string
    {
        if ($registration->dns_configured && $registration->ssl_configured) {
            return 'ready';
        }
        
        if ($registration->dns_configured || $registration->ssl_configured) {
            return 'partial';
        }
        
        return 'pending';
    }
}

