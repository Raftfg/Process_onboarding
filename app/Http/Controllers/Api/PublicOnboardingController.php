<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OnboardingService;
use App\Services\TenantService;
use App\Services\ActivationService;
use App\Mail\ActivationMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;

use OpenApi\Attributes as OA;

class PublicOnboardingController extends Controller
{
    protected $onboardingService;
    protected $tenantService;

    public function __construct(OnboardingService $onboardingService, TenantService $tenantService)
    {
        $this->onboardingService = $onboardingService;
        $this->tenantService = $tenantService;
    }

    #[OA\Post(
        path: "/api/onboarding/create",
        summary: "Créer un onboarding via API publique",
        tags: ["Onboarding Public"],
        security: [["ApiKey" => []], ["AppName" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "organization", ref: "#/components/schemas/Organization"),
                    new OA\Property(property: "admin", ref: "#/components/schemas/Admin"),
                    new OA\Property(
                        property: "options",
                        properties: [
                            new OA\Property(property: "send_welcome_email", type: "boolean", example: true),
                            new OA\Property(property: "auto_login", type: "boolean", example: false)
                        ],
                        type: "object"
                    ),
                    new OA\Property(property: "metadata", type: "object", example: ["external_id" => "CRM-789"])
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Onboarding créé avec succès",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            properties: [
                                new OA\Property(property: "subdomain", type: "string", example: "mon-entreprise"),
                                new OA\Property(property: "database_name", type: "string", example: "tenant_mon_entreprise"),
                                new OA\Property(property: "url", type: "string", example: "http://mon-entreprise.localhost:8000"),
                                new OA\Property(property: "admin_email", type: "string", example: "admin@exemple.com"),
                                new OA\Property(property: "created_at", type: "string", format: "date-time")
                            ],
                            type: "object"
                        )
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Erreur de validation"),
            new OA\Response(response: 401, description: "Non autorisé (Clé API ou App Name invalide)"),
            new OA\Response(response: 500, description: "Erreur serveur")
        ]
    )]

    /**
     * Créer un onboarding via API publique
     * 
     * POST /api/onboarding/create
     */
    public function create(Request $request)
    {
        // Valider les données
        $validator = Validator::make($request->all(), [
            'organization.name' => 'required|string|max:255',
            'organization.address' => 'nullable|string|max:500',
            'organization.phone' => 'nullable|string|max:20',
            'organization.email' => 'nullable|email|max:255',
            'admin.first_name' => 'nullable|string|max:255',
            'admin.last_name' => 'nullable|string|max:255',
            'admin.email' => 'nullable|email|max:255',
            'admin.password' => 'nullable|string|min:8',
            'options.send_welcome_email' => 'nullable|boolean',
            'options.auto_login' => 'nullable|boolean',
        ]);

        // Validation personnalisée pour s'assurer d'avoir au moins un email
        $validator->after(function ($validator) use ($request) {
            if (!$request->input('admin.email') && !$request->input('organization.email')) {
                $validator->errors()->add('email', 'Une adresse email est requise (dans organization.email ou admin.email)');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            // Préparer les données au format attendu par OnboardingService
            $onboardingData = [
                'step1' => [
                    'organization_name' => $request->input('organization.name'),
                    'organization_address' => $request->input('organization.address'),
                    'organization_phone' => $request->input('organization.phone'),
                    'organization_email' => $request->input('organization.email'),
                ],
                'step2' => [
                    'admin_first_name' => $request->input('admin.first_name'),
                    'admin_last_name' => $request->input('admin.last_name'),
                    'admin_email' => $request->input('admin.email'),
                    'admin_password' => $request->input('admin.password'),
                ]
            ];

            // Traiter l'onboarding
            $email = $onboardingData['step2']['admin_email'] ?? $onboardingData['step1']['organization_email'];
            $organizationName = $onboardingData['step1']['organization_name'];
            $metadata = $request->input('metadata', []);
            $result = $this->onboardingService->processOnboarding($email, $organizationName, $metadata);

            // Si des données d'admin supplémentaires étaient fournies, on pourrait les mettre à jour ici
            $this->updateAdminInfoIfProvided($result['subdomain'], $onboardingData['step2']);

            // Connexion automatique si demandée
            if ($request->input('options.auto_login', false)) {
                $this->autoLoginAfterOnboarding($result);
            }

            // Envoyer l'email d'activation si demandé (activé par défaut)
            $sendEmail = $request->input('options.send_welcome_email', true);
            if ($sendEmail) {
                try {
                    Mail::to($email)->send(
                        new ActivationMail($email, $organizationName, $result['activation_token'])
                    );
                    Log::info("Email d'activation envoyé via API pour: {$email}");
                } catch (\Exception $e) {
                    Log::error("Échec de l'envoi de l'email d'activation via API: " . $e->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'subdomain' => $result['subdomain'],
                    'database_name' => $result['database'],
                    'url' => $result['url'],
                    'admin_email' => $result['email'],
                    'created_at' => now()->toIso8601String()
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Erreur création onboarding API: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de l\'onboarding: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir le statut d'un onboarding
     * 
     * GET /api/onboarding/status/{subdomain}
     */
    #[OA\Get(
        path: "/api/onboarding/status/{subdomain}",
        summary: "Obtenir le statut d'un onboarding",
        tags: ["Onboarding Public"],
        security: [["ApiKey" => []], ["AppName" => []]],
        parameters: [
            new OA\Parameter(
                name: "subdomain",
                in: "path",
                required: true,
                description: "Le sous-domaine du tenant",
                schema: new OA\Schema(type: "string")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Statut récupéré",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            properties: [
                                new OA\Property(property: "subdomain", type: "string", example: "mon-entreprise"),
                                new OA\Property(property: "status", type: "string", example: "completed"),
                                new OA\Property(property: "database_name", type: "string", example: "tenant_mon_entreprise"),
                                new OA\Property(property: "created_at", type: "string", format: "date-time")
                            ],
                            type: "object"
                        )
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Onboarding non trouvé")
        ]
    )]
    public function status($subdomain)
    {
        try {
            $onboarding = \App\Models\OnboardingSession::where('subdomain', $subdomain)
                ->first();

            if (!$onboarding) {
                return response()->json([
                    'success' => false,
                    'message' => 'Onboarding non trouvé'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'subdomain' => $onboarding->subdomain,
                    'status' => $onboarding->status,
                    'database_name' => $onboarding->database_name,
                    'created_at' => $onboarding->created_at->toIso8601String()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur récupération statut onboarding: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du statut'
            ], 500);
        }
    }

    /**
     * Obtenir les informations d'un tenant
     * 
     * GET /api/tenant/{subdomain}
     */
    public function getTenant($subdomain)
    {
        try {
            $onboarding = \App\Models\OnboardingSession::where('subdomain', $subdomain)
                ->where('status', 'completed')
                ->first();

            if (!$onboarding) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant non trouvé'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'subdomain' => $onboarding->subdomain,
                    'organization_name' => $onboarding->organization_name,
                    'organization_address' => $onboarding->organization_address,
                    'organization_phone' => $onboarding->organization_phone,
                    'organization_email' => $onboarding->organization_email,
                    'admin_email' => $onboarding->admin_email,
                    'database_name' => $onboarding->database_name,
                    'status' => $onboarding->status,
                    'created_at' => $onboarding->created_at->toIso8601String()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur récupération tenant: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du tenant'
            ], 500);
        }
    }

    /**
     * Connecte automatiquement l'utilisateur admin après un onboarding réussi
     */
    protected function autoLoginAfterOnboarding(array $result): void
    {
        try {
            $subdomain = $result['subdomain'] ?? null;
            $adminEmail = $result['admin_email'] ?? null;
            
            if (!$subdomain || !$adminEmail) {
                Log::warning('Impossible de connecter automatiquement: données manquantes');
                return;
            }

            // Basculer vers la base du tenant
            $databaseName = $this->tenantService->getTenantDatabase($subdomain);
            if ($databaseName) {
                $this->tenantService->switchToTenantDatabase($databaseName);
            }

            // Récupérer l'utilisateur depuis la base du tenant
            $user = \App\Models\User::where('email', $adminEmail)->first();
            
            if ($user) {
                // Note: Pour une API, on ne peut pas vraiment "connecter" l'utilisateur
                // car il n'y a pas de session HTTP. On retourne plutôt un token ou une URL de connexion
                Log::info("Utilisateur trouvé pour auto-login: {$adminEmail}");
            } else {
                Log::warning("Utilisateur non trouvé pour auto-login: {$adminEmail}");
            }
        } catch (\Exception $e) {
            Log::error('Erreur lors de la connexion automatique: ' . $e->getMessage());
        }
    }
    /**
     * Met à jour les informations de l'administrateur si elles sont fournies via l'API
     */
    protected function updateAdminInfoIfProvided(string $subdomain, array $adminData): void
    {
        try {
            $session = \App\Models\OnboardingSession::where('subdomain', $subdomain)->first();
            if ($session) {
                $session->update([
                    'admin_first_name' => $adminData['admin_first_name'] ?? $session->admin_first_name,
                    'admin_last_name' => $adminData['admin_last_name'] ?? $session->admin_last_name,
                ]);
                Log::info("Informations admin mises à jour pour le tenant: {$subdomain}");
            }
        } catch (\Exception $e) {
            Log::warning("Erreur lors de la mise à jour des infos admin: " . $e->getMessage());
        }
    }
}
