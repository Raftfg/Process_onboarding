<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OnboardingService;
use App\Services\TenantService;
use App\Services\ActivationService;
use App\Services\OrganizationNameGenerator;
use App\Models\ApiKey;
use App\Mail\ActivationMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

use OpenApi\Attributes as OA;

class OnboardingApiController extends Controller
{
    protected $onboardingService;
    protected $tenantService;
    protected $activationService;
    protected $organizationNameGenerator;

    public function __construct(OnboardingService $onboardingService, TenantService $tenantService, ActivationService $activationService, OrganizationNameGenerator $organizationNameGenerator)
    {
        $this->onboardingService = $onboardingService;
        $this->tenantService = $tenantService;
        $this->activationService = $activationService;
        $this->organizationNameGenerator = $organizationNameGenerator;
    }

    // Ancien endpoint d'onboarding externe via clés API (déprécié)
    public function processAsync(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email|max:255',
                'organization_name' => 'nullable|string|max:255',
            ]);

            $email = $validated['email'];
            $organizationName = $validated['organization_name'] ?? null;
            
            // Générer automatiquement le nom d'organisation s'il est vide ou null
            if (empty(trim($organizationName ?? ''))) {
                $organizationName = $this->organizationNameGenerator->generate('auto', [
                    'email' => $email,
                    'metadata' => [],
                ]);
                Log::info('Nom d\'organisation généré automatiquement dans processAsync', [
                    'email' => $email,
                    'generated_name' => $organizationName,
                ]);
            }

            // Générer un identifiant unique pour le suivi
            $sessionId = Session::getId();
            Session::put('onboarding_session_id', $sessionId);
            Session::put('onboarding_status', 'processing');
            Session::put('onboarding_email', $email);
            Session::put('onboarding_organization', $organizationName);

            // Traiter l'onboarding
            try {
                $result = $this->onboardingService->processOnboarding($email, $organizationName);
                
                // Vérifier que le token d'activation a été créé
                if (empty($result['activation_token'])) {
                    Log::error('Token d\'activation manquant dans le résultat', ['result' => $result]);
                    throw new \Exception('Erreur lors de la création du token d\'activation');
                }
                
                Log::info('Onboarding traité avec succès', [
                    'email' => $email,
                    'subdomain' => $result['subdomain'] ?? null,
                    'has_token' => !empty($result['activation_token']),
                ]);
                
                // Envoyer l'email d'activation
                try {
                    $mailDriver = config('mail.default');
                    Log::info('Tentative d\'envoi d\'email', [
                        'email' => $email,
                        'driver' => $mailDriver,
                        'from' => config('mail.from.address'),
                    ]);
                    
                    Mail::to($email)->send(
                        new ActivationMail($email, $organizationName, $result['activation_token'])
                    );
                    
                    Log::info('Email d\'activation envoyé avec succès', [
                        'email' => $email,
                        'token' => substr($result['activation_token'], 0, 10) . '...',
                    ]);
                } catch (\Exception $e) {
                    Log::error('Erreur lors de l\'envoi de l\'email d\'activation', [
                        'email' => $email,
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    // Ne pas faire échouer le processus si l'email échoue, mais logger l'erreur
                }

                Session::put('onboarding_status', 'completed');
                Session::put('onboarding_result', $result);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Votre espace a été créé avec succès. Vous allez être redirigé vers votre dashboard.',
                    'session_id' => $sessionId,
                    'result' => [
                        'subdomain' => $result['subdomain'],
                        'email' => $email,
                        'activation_token' => $result['activation_token'] ?? null,
                        'auto_login_token' => $result['auto_login_token'] ?? null,
                        'user_id' => $result['user_id'] ?? null,
                    ]
                ]);
            } catch (\Exception $e) {
                Log::error('Erreur lors de l\'onboarding: ' . $e->getMessage(), [
                    'exception' => get_class($e),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                // Formater l'erreur pour l'utilisateur
                $userMessage = \App\Helpers\ErrorFormatter::formatException($e);
                
                Session::put('onboarding_status', 'failed');
                Session::put('onboarding_error', $userMessage);
                
                return response()->json([
                    'success' => false,
                    'message' => $userMessage,
                    'session_id' => $sessionId
                ], 500);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur API onboarding: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Formater l'erreur pour l'utilisateur
            $userMessage = \App\Helpers\ErrorFormatter::formatException($e);
            
            return response()->json([
                'success' => false,
                'message' => $userMessage
            ], 500);
        }
    }



    public function status($sessionId)
    {
        $status = Session::get('onboarding_status', 'pending');
        $result = Session::get('onboarding_result');
        $error = Session::get('onboarding_error');

        return response()->json([
            'status' => $status,
            'result' => $result,
            'error' => $error
        ]);
    }


    /**
     * Endpoint pour l'onboarding depuis une application externe (Secteur)
     */
    public function externalStore(Request $request)
    {
        try {
            // Récupérer la clé API depuis le middleware
            $apiKeyModel = $request->get('api_key_model');
            
            // Si pas de clé API (fallback sur env), utiliser les règles par défaut
            $validationRules = [
                'email' => 'required|email|max:255',
                'organization_name' => 'nullable|string|max:255', // Optionnel par défaut
                'callback_url' => 'nullable|url',
                'metadata' => 'nullable|array',
                'generate_api_key' => 'nullable|boolean',
            ];

            // Si on a une clé API avec configuration, utiliser ses règles
            if ($apiKeyModel instanceof ApiKey) {
                $apiValidationRules = $apiKeyModel->getValidationRules();
                // Fusionner les règles (les règles de l'API key prennent priorité)
                $validationRules = array_merge($validationRules, $apiValidationRules);
            }

            $validated = $request->validate($validationRules);

            // Récupérer le nom de l'application source depuis le header
            // Supporte X-App-Name ou X-Source-App
            $sourceAppName = $request->header('X-App-Name') ?? $request->header('X-Source-App');

            if (!$sourceAppName) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le header X-App-Name est obligatoire.'
                ], 400);
            }

            // Gérer organization_name : générer si non fourni et non requis
            $organizationName = $validated['organization_name'] ?? null;
            
            if (empty($organizationName) && $apiKeyModel instanceof ApiKey) {
                // Si organization_name n'est pas requis, générer automatiquement
                if (!$apiKeyModel->shouldRequireOrganizationName()) {
                    $strategy = $apiKeyModel->getOrganizationNameGenerationStrategy();
                    $template = $apiKeyModel->getOrganizationNameTemplate();
                    
                    $context = [
                        'email' => $validated['email'],
                        'metadata' => $validated['metadata'] ?? [],
                    ];
                    
                    if ($strategy === 'custom' && $template) {
                        $context['template'] = $template;
                    }
                    
                    $organizationName = $this->organizationNameGenerator->generate($strategy, $context);
                    
                    Log::info('Nom d\'organisation généré automatiquement', [
                        'strategy' => $strategy,
                        'generated_name' => $organizationName,
                        'email' => $validated['email'],
                    ]);
                }
            }

            // Si toujours pas de nom d'organisation, erreur
            if (empty($organizationName)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le champ organization_name est requis ou doit être généré automatiquement.'
                ], 422);
            }

            // NOTE: Cette méthode est dépréciée. Utiliser OnboardingRegistrationController::register() à la place
            // Gardée pour rétrocompatibilité temporaire
            
            // Rediriger vers la nouvelle API si possible
            // Pour l'instant, retourner une erreur indiquant d'utiliser la nouvelle API
            return response()->json([
                'success' => false,
                'message' => 'Cet endpoint est déprécié. Utilisez POST /api/v1/onboarding/register avec X-Master-Key.',
                'new_endpoint' => '/api/v1/onboarding/register',
                'note' => 'Le microservice ne crée plus les tenants. Il enregistre uniquement les métadonnées et génère les sous-domaines.',
            ], 410); // 410 Gone

            return response()->json([
                'success' => true,
                'message' => 'Onboarding externe initié avec succès',
                'result' => $result
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur onboarding externe: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
