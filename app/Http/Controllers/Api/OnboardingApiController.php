<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OnboardingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;

class OnboardingApiController extends Controller
{
    protected $onboardingService;

    public function __construct(OnboardingService $onboardingService)
    {
        $this->onboardingService = $onboardingService;
    }

    public function complete(Request $request)
    {
        try {
            $onboardingData = Session::get('onboarding_data');
            
            if (!$onboardingData || !isset($onboardingData['step1']) || !isset($onboardingData['step2'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Données d\'onboarding incomplètes'
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
}
