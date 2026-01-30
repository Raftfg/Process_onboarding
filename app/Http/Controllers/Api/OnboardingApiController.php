<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OnboardingService;
use App\Services\TenantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class OnboardingApiController extends Controller
{
    protected $onboardingService;
    protected $tenantService;

    public function __construct(OnboardingService $onboardingService, TenantService $tenantService)
    {
        $this->onboardingService = $onboardingService;
        $this->tenantService = $tenantService;
    }

    public function complete(Request $request)
    {
        try {
            $onboardingData = Session::get('onboarding_data');
            
            // Debug: logger les données de session pour diagnostiquer
            Log::info('Données de session onboarding:', [
                'has_data' => !empty($onboardingData),
                'has_step1' => isset($onboardingData['step1']),
                'has_step2' => isset($onboardingData['step2']),
                'session_id' => Session::getId(),
                'all_session_keys' => Session::all()
            ]);
            
            if (!$onboardingData || !isset($onboardingData['step1']) || !isset($onboardingData['step2'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Données d\'onboarding incomplètes',
                    'debug' => [
                        'has_data' => !empty($onboardingData),
                        'has_step1' => isset($onboardingData['step1']),
                        'has_step2' => isset($onboardingData['step2']),
                    ]
                ], 400);
            }

            // Générer un identifiant unique pour le suivi
            $sessionId = Session::getId();
            Session::put('onboarding_session_id', $sessionId);
            Session::put('onboarding_status', 'processing');

            // Traiter l'onboarding (synchrone pour cette version)
            try {
                $result = $this->onboardingService->processOnboarding($onboardingData);
                Session::put('onboarding_status', 'completed');
                Session::put('onboarding_result', $result);
                
                // Connexion automatique de l'utilisateur admin après onboarding
                $this->autoLoginAfterOnboarding($result);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Onboarding terminé avec succès',
                    'session_id' => $sessionId,
                    'result' => $result
                ]);
            } catch (\Exception $e) {
                Log::error('Erreur lors de l\'onboarding: ' . $e->getMessage());
                Session::put('onboarding_status', 'failed');
                Session::put('onboarding_error', $e->getMessage());
                
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur lors de l\'onboarding: ' . $e->getMessage(),
                    'session_id' => $sessionId
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Erreur API onboarding: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue: ' . $e->getMessage()
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
                session(['current_subdomain' => $subdomain]);
            }

            // Récupérer l'utilisateur depuis la base du tenant
            $user = \App\Models\User::where('email', $adminEmail)->first();
            
            if ($user) {
                // Connecter l'utilisateur avec "remember" pour persister la session
                Auth::login($user, true);
                
                // Régénérer la session pour sécuriser
                Session::regenerate();
                
                // Sauvegarder la session pour s'assurer qu'elle est persistée
                Session::save();
                
                Log::info("Connexion automatique réussie pour: {$adminEmail}", [
                    'user_id' => $user->id,
                    'session_id' => Session::getId()
                ]);
            } else {
                Log::warning("Utilisateur non trouvé pour connexion automatique: {$adminEmail}");
            }
        } catch (\Exception $e) {
            Log::error('Erreur lors de la connexion automatique: ' . $e->getMessage());
            // Ne pas faire échouer l'onboarding si la connexion automatique échoue
        }
    }
}
