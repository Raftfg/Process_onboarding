<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Services\DatabaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class ApplicationController extends Controller
{
    protected $databaseService;

    public function __construct(DatabaseService $databaseService)
    {
        $this->databaseService = $databaseService;
    }

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

            // Créer la base de données pour cette application
            $databaseCreated = false;
            $dbResult = null;
            $dbError = null;

            try {
                $dbResult = $this->databaseService->createApplicationDatabase(
                    $result['id'],
                    $validated['app_name']
                );
                $databaseCreated = true;
            } catch (\Exception $dbException) {
                $dbError = $dbException->getMessage();
                Log::warning('Échec de la création de la base de données, mais application créée', [
                    'app_id' => $result['app_id'],
                    'error' => $dbError,
                ]);
            }

            // Préparer la réponse
            $response = [
                'success' => true,
                'message' => $databaseCreated 
                    ? 'Application enregistrée avec succès' 
                    : 'Application enregistrée, mais la création de la base de données a échoué',
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
                ],
            ];

            if ($databaseCreated) {
                $appDatabase = $dbResult['database'];
                $plainPassword = $dbResult['plain_password'];
                $connectionString = $this->databaseService->getConnectionString($appDatabase, $plainPassword);

                $response['database'] = [
                    'name' => $appDatabase->database_name,
                    'host' => $appDatabase->db_host,
                    'port' => $appDatabase->db_port,
                    'username' => $appDatabase->db_username,
                    'password' => $plainPassword, // Affiché une seule fois
                    'connection_string' => $connectionString,
                ];
                $response['warnings'][] = '⚠️ IMPORTANT: Sauvegardez les credentials de la base de données ! Le mot de passe ne sera plus jamais affiché.';

                Log::info('Nouvelle application enregistrée avec base de données', [
                    'app_id' => $result['app_id'],
                    'app_name' => $result['app_name'],
                    'database_name' => $appDatabase->database_name,
                    'ip' => $request->ip(),
                ]);
            } else {
                $response['database'] = null;
                $response['database_error'] = 'La création de la base de données a échoué. Vous pouvez réessayer avec POST /api/v1/applications/{app_id}/retry-database';
                $response['warnings'][] = '⚠️ La base de données n\'a pas pu être créée. Réessayez plus tard ou contactez le support.';
            }

            return response()->json($response, $databaseCreated ? 201 : 207); // 207 = Multi-Status

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
