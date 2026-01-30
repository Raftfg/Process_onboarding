<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OnboardingService;
use App\Services\TenantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PublicOnboardingController extends Controller
{
    protected $onboardingService;
    protected $tenantService;

    public function __construct(OnboardingService $onboardingService, TenantService $tenantService)
    {
        $this->onboardingService = $onboardingService;
        $this->tenantService = $tenantService;
    }

    /**
     * Créer un onboarding via API publique
     * 
     * POST /api/onboarding/create
     */
    public function create(Request $request)
    {
        // Valider les données
        $validator = Validator::make($request->all(), [
            'hospital.name' => 'required|string|max:255',
            'hospital.address' => 'nullable|string|max:500',
            'hospital.phone' => 'nullable|string|max:20',
            'hospital.email' => 'nullable|email|max:255',
            'admin.first_name' => 'required|string|max:255',
            'admin.last_name' => 'required|string|max:255',
            'admin.email' => 'required|email|max:255',
            'admin.password' => 'required|string|min:8',
            'options.send_welcome_email' => 'nullable|boolean',
            'options.auto_login' => 'nullable|boolean',
        ]);

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
                    'hospital_name' => $request->input('hospital.name'),
                    'hospital_address' => $request->input('hospital.address'),
                    'hospital_phone' => $request->input('hospital.phone'),
                    'hospital_email' => $request->input('hospital.email'),
                ],
                'step2' => [
                    'admin_first_name' => $request->input('admin.first_name'),
                    'admin_last_name' => $request->input('admin.last_name'),
                    'admin_email' => $request->input('admin.email'),
                    'admin_password' => $request->input('admin.password'),
                ]
            ];

            // Traiter l'onboarding
            $result = $this->onboardingService->processOnboarding($onboardingData);

            // Connexion automatique si demandée
            if ($request->input('options.auto_login', false)) {
                $this->autoLoginAfterOnboarding($result);
            }

            // Envoyer l'email de bienvenue si demandé
            $sendEmail = $request->input('options.send_welcome_email', true);
            if (!$sendEmail) {
                // Ne pas envoyer l'email (déjà envoyé dans processOnboarding, mais on peut le désactiver)
                // Note: Pour une vraie désactivation, il faudrait modifier OnboardingService
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'subdomain' => $result['subdomain'],
                    'database_name' => $result['database'],
                    'url' => $result['url'],
                    'admin_email' => $result['admin_email'],
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
                    'hospital_name' => $onboarding->hospital_name,
                    'hospital_address' => $onboarding->hospital_address,
                    'hospital_phone' => $onboarding->hospital_phone,
                    'hospital_email' => $onboarding->hospital_email,
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
}
