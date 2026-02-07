<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Services\DatabaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use OpenApi\Attributes as OA;

class ApplicationController extends Controller
{
    protected $databaseService;

    public function __construct(DatabaseService $databaseService)
    {
        $this->databaseService = $databaseService;
    }

    #[OA\Post(
        path: "/api/v1/applications/register",
        summary: "Enregistrer une nouvelle application cliente",
        description: "Permet à une application cliente de s'enregistrer et d'obtenir une master key pour utiliser l'API d'onboarding. **Note importante** : L'enregistrement ne crée pas de base de données. Seule la master key est nécessaire pour démarrer un onboarding.",
        tags: ["Applications"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["app_name", "display_name", "contact_email"],
                properties: [
                    new OA\Property(property: "app_name", type: "string", example: "mon-application", description: "Nom unique de l'application (alphanumérique et tirets uniquement)"),
                    new OA\Property(property: "display_name", type: "string", example: "Mon Application", description: "Nom d'affichage de l'application"),
                    new OA\Property(property: "contact_email", type: "string", format: "email", example: "dev@monapp.com", description: "Email de contact pour l'application"),
                    new OA\Property(property: "website", type: "string", format: "uri", nullable: true, example: "https://monapp.com", description: "Site web de l'application (optionnel)"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Application enregistrée avec succès",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Application enregistrée avec succès"),
                        new OA\Property(
                            property: "application",
                            type: "object",
                            properties: [
                                new OA\Property(property: "app_id", type: "string", example: "app_abc123"),
                                new OA\Property(property: "app_name", type: "string", example: "mon-application"),
                                new OA\Property(property: "display_name", type: "string", example: "Mon Application"),
                                new OA\Property(property: "contact_email", type: "string", format: "email", example: "dev@monapp.com"),
                                new OA\Property(property: "website", type: "string", format: "uri", nullable: true, example: "https://monapp.com"),
                                new OA\Property(property: "created_at", type: "string", format: "date-time", example: "2026-02-07T10:30:00Z"),
                            ]
                        ),
                        new OA\Property(property: "master_key", type: "string", example: "mk_live_xyz789...", description: "⚠️ IMPORTANT: Stockez cette clé en sécurité, elle ne sera plus jamais affichée. Utilisez-la dans le header X-Master-Key pour démarrer un onboarding."),
                        new OA\Property(
                            property: "warnings",
                            type: "array",
                            items: new OA\Items(type: "string"),
                            example: [
                                "⚠️ IMPORTANT: Sauvegardez la master_key immédiatement ! Elle ne sera plus jamais affichée.",
                                "💡 Vous pouvez maintenant utiliser cette master_key pour démarrer un onboarding avec POST /api/v1/onboarding/start"
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: "Erreur de validation",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: false),
                        new OA\Property(property: "message", type: "string", example: "Erreur de validation"),
                        new OA\Property(
                            property: "errors",
                            type: "object",
                            properties: [
                                new OA\Property(
                                    property: "app_name",
                                    type: "array",
                                    items: new OA\Items(type: "string"),
                                    example: ["Ce nom d'application est déjà utilisé. Veuillez choisir un autre nom."]
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 429, description: "Trop de tentatives d'enregistrement"),
        ]
    )]
    /**
     * Enregistre une nouvelle application (publique, sans authentification)
     * 
     * POST /api/v1/applications/register
     */
    public function register(Request $request)
    {
        // Rate limiting pour éviter les abus
        $key = 'application_register:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'success' => false,
                'message' => 'Trop de tentatives. Veuillez réessayer dans quelques minutes.'
            ], 429);
        }
        RateLimiter::hit($key, 3600); // 5 tentatives par heure

        try {
            $validated = $request->validate([
                'app_name' => 'required|string|max:50|alpha_dash|unique:applications,app_name',
                'display_name' => 'required|string|max:255',
                'contact_email' => 'required|email|max:255',
                'website' => 'nullable|url|max:255',
            ], [
                'app_name.unique' => 'Ce nom d\'application est déjà utilisé. Veuillez choisir un autre nom.',
                'app_name.alpha_dash' => 'Le nom d\'application ne peut contenir que des lettres, chiffres, tirets et underscores.',
                'app_name.max' => 'Le nom d\'application ne peut pas dépasser 50 caractères.',
                'contact_email.email' => 'L\'adresse email n\'est pas valide.',
                'website.url' => 'L\'URL du site web n\'est pas valide.',
            ]);

            // Vérifier que le nom d'application n'est pas réservé
            $reservedNames = ['admin', 'api', 'www', 'mail', 'ftp', 'localhost', 'test', 'dev', 'staging', 'prod'];
            if (in_array(strtolower($validated['app_name']), $reservedNames)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce nom d\'application est réservé. Veuillez en choisir un autre.'
                ], 422);
            }

            // Vérifier la disponibilité
            if (!Application::isAppNameAvailable($validated['app_name'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce nom d\'application est déjà utilisé.'
                ], 422);
            }

            // Enregistrer l'application
            $result = Application::register(
                $validated['app_name'],
                $validated['display_name'],
                $validated['contact_email'],
                $validated['website'] ?? null
            );

            Log::info('Nouvelle application enregistrée', [
                'app_id' => $result['app_id'],
                'app_name' => $result['app_name'],
                'ip' => $request->ip(),
            ]);

            // Préparer la réponse
            return response()->json([
                'success' => true,
                'message' => 'Application enregistrée avec succès',
                'application' => [
                    'app_id' => $result['app_id'],
                    'app_name' => $result['app_name'],
                    'display_name' => $result['display_name'],
                    'contact_email' => $result['contact_email'],
                    'website' => $result['website'],
                    'created_at' => $result['created_at']->toIso8601String(),
                ],
                'master_key' => $result['master_key'],
                'warnings' => [
                    '⚠️ IMPORTANT: Sauvegardez la master_key immédiatement ! Elle ne sera plus jamais affichée.',
                    '💡 Vous pouvez maintenant utiliser cette master_key pour démarrer un onboarding avec POST /api/v1/onboarding/start',
                ],
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'enregistrement d\'application: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'enregistrement. Veuillez réessayer plus tard.'
            ], 500);
        }
    }

    #[OA\Get(
        path: "/api/v1/applications/{app_id}",
        summary: "Récupérer les informations d'une application",
        description: "Retourne les détails de l'application (nom, email, statut, dates).",
        tags: ["Applications"],
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
                description: "Informations de l'application",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(
                            property: "application",
                            type: "object",
                            properties: [
                                new OA\Property(property: "app_id", type: "string", example: "app_abc123"),
                                new OA\Property(property: "app_name", type: "string", example: "mon-application"),
                                new OA\Property(property: "display_name", type: "string", example: "Mon Application"),
                                new OA\Property(property: "contact_email", type: "string", format: "email"),
                                new OA\Property(property: "website", type: "string", format: "uri", nullable: true),
                                new OA\Property(property: "is_active", type: "boolean", example: true),
                                new OA\Property(property: "created_at", type: "string", format: "date-time"),
                                new OA\Property(property: "last_used_at", type: "string", format: "date-time", nullable: true),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Master key invalide ou absente"),
            new OA\Response(response: 404, description: "Application non trouvée"),
        ]
    )]
    /**
     * Récupère les informations d'une application (avec master_key)
     * 
     * GET /api/v1/applications/{app_id}
     */
    public function show(Request $request, string $appId)
    {
        // L'application est récupérée via le middleware MasterKeyAuth
        $application = $request->get('application');

        return response()->json([
            'success' => true,
            'application' => [
                'app_id' => $application->app_id,
                'app_name' => $application->app_name,
                'display_name' => $application->display_name,
                'contact_email' => $application->contact_email,
                'website' => $application->website,
                'is_active' => $application->is_active,
                'created_at' => $application->created_at->toIso8601String(),
                'last_used_at' => $application->last_used_at?->toIso8601String(),
            ],
        ]);
    }

    #[OA\Post(
        path: "/api/v1/applications/{app_id}/retry-database",
        summary: "Réessayer la création de la base de données",
        description: "Tente de créer la base de données pour une application qui n'en a pas encore. Utile si la création initiale a échoué.",
        tags: ["Applications"],
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
                response: 201,
                description: "Base de données créée avec succès",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Base de données créée avec succès"),
                        new OA\Property(
                            property: "database",
                            type: "object",
                            properties: [
                                new OA\Property(property: "name", type: "string", example: "app_monapp_db"),
                                new OA\Property(property: "host", type: "string", example: "localhost"),
                                new OA\Property(property: "port", type: "integer", example: 3306),
                                new OA\Property(property: "username", type: "string", example: "app_monapp_user"),
                                new OA\Property(property: "password", type: "string", example: "secure_password", description: "⚠️ Affiché une seule fois"),
                                new OA\Property(property: "connection_string", type: "string", example: "mysql://user:pass@host:port/dbname"),
                            ]
                        ),
                        new OA\Property(
                            property: "warnings",
                            type: "array",
                            items: new OA\Items(type: "string"),
                            example: ["⚠️ IMPORTANT: Sauvegardez les credentials de la base de données !"]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 400, description: "L'application a déjà une base de données"),
            new OA\Response(response: 401, description: "Master key invalide ou absente"),
            new OA\Response(response: 404, description: "Application non trouvée"),
            new OA\Response(response: 500, description: "Erreur lors de la création de la base de données"),
        ]
    )]
    /**
     * Réessaie la création de la base de données pour une application existante
     * 
     * POST /api/v1/applications/{app_id}/retry-database
     */
    public function retryDatabase(Request $request, string $appId)
    {
        $application = $request->get('application');

        // Vérifier que l'application existe (devrait être fournie par le middleware)
        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => 'Application non trouvée. Vérifiez votre master_key et l\'app_id dans l\'URL.',
            ], 404);
        }

        // Vérifier si la base de données existe déjà
        if ($application->hasDatabase()) {
            return response()->json([
                'success' => false,
                'message' => 'Cette application a déjà une base de données configurée.',
            ], 400);
        }

        try {
            // Créer la base de données
            $dbResult = $this->databaseService->createApplicationDatabase(
                $application->id,
                $application->app_name
            );

            $appDatabase = $dbResult['database'];
            $plainPassword = $dbResult['plain_password'];
            $connectionString = $this->databaseService->getConnectionString($appDatabase, $plainPassword);

            Log::info('Base de données créée avec succès (retry)', [
                'app_id' => $application->app_id,
                'database_name' => $appDatabase->database_name,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Base de données créée avec succès',
                'database' => [
                    'name' => $appDatabase->database_name,
                    'host' => $appDatabase->db_host,
                    'port' => $appDatabase->db_port,
                    'username' => $appDatabase->db_username,
                    'password' => $plainPassword, // Affiché une seule fois
                    'connection_string' => $connectionString,
                ],
                'warnings' => [
                    '⚠️ IMPORTANT: Sauvegardez les credentials de la base de données ! Le mot de passe ne sera plus jamais affiché.',
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la création de la base de données (retry): ' . $e->getMessage(), [
                'app_id' => $application->app_id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Impossible de créer la base de données: ' . $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Post(
        path: "/api/v1/applications/regenerate-master-key",
        summary: "Régénérer la master key",
        description: "Génère une nouvelle master key pour une application existante. L'ancienne master key devient immédiatement invalide. Vérifie l'identité via app_name + contact_email.",
        tags: ["Applications"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["app_name", "contact_email"],
                properties: [
                    new OA\Property(property: "app_name", type: "string", example: "mon-application", description: "Nom unique de l'application"),
                    new OA\Property(property: "contact_email", type: "string", format: "email", example: "dev@monapp.com", description: "Email de contact enregistré"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Master key régénérée avec succès",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Master key régénérée avec succès"),
                        new OA\Property(
                            property: "application",
                            type: "object",
                            properties: [
                                new OA\Property(property: "app_id", type: "string", example: "app_abc123"),
                                new OA\Property(property: "app_name", type: "string", example: "mon-application"),
                            ]
                        ),
                        new OA\Property(property: "master_key", type: "string", example: "mk_live_xyz789...", description: "⚠️ IMPORTANT: Sauvegardez immédiatement, elle ne sera plus affichée"),
                        new OA\Property(
                            property: "warnings",
                            type: "array",
                            items: new OA\Items(type: "string"),
                            example: ["⚠️ IMPORTANT: Sauvegardez la nouvelle master_key immédiatement !", "⚠️ L'ancienne master_key est maintenant invalide."]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Aucune application trouvée avec ce nom et cet email"),
            new OA\Response(response: 422, description: "Erreur de validation"),
        ]
    )]
    /**
     * Régénère le master_key pour une application existante (vérification par email)
     * 
     * POST /api/v1/applications/regenerate-master-key
     */
    public function regenerateMasterKey(Request $request)
    {
        $validated = $request->validate([
            'app_name' => 'required|string|exists:applications,app_name',
            'contact_email' => 'required|email',
        ]);

        // Vérifier que l'email correspond à l'application
        $application = Application::where('app_name', $validated['app_name'])
            ->where('contact_email', $validated['contact_email'])
            ->first();

        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune application trouvée avec ce nom et cet email.',
            ], 404);
        }

        // Générer un nouveau master_key
        $newMasterKey = 'mk_' . \Illuminate\Support\Str::random(48);
        $newMasterKeyHash = \Illuminate\Support\Facades\Hash::make($newMasterKey);

        // Mettre à jour l'application
        $application->update([
            'master_key' => $newMasterKeyHash,
        ]);

        Log::info('Master key régénérée pour application', [
            'app_id' => $application->app_id,
            'app_name' => $application->app_name,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Master key régénérée avec succès',
            'application' => [
                'app_id' => $application->app_id,
                'app_name' => $application->app_name,
            ],
            'master_key' => $newMasterKey,
            'warnings' => [
                '⚠️ IMPORTANT: Sauvegardez la nouvelle master_key immédiatement ! Elle ne sera plus jamais affichée.',
                '⚠️ L\'ancienne master_key est maintenant invalide.',
            ],
        ], 200);
    }
}
