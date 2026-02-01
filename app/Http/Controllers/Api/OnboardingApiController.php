<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OnboardingService;
use App\Services\TenantService;
use App\Services\ActivationService;
use App\Mail\ActivationMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class OnboardingApiController extends Controller
{
    protected $onboardingService;
    protected $tenantService;
    protected $activationService;

    public function __construct(OnboardingService $onboardingService, TenantService $tenantService, ActivationService $activationService)
    {
        $this->onboardingService = $onboardingService;
        $this->tenantService = $tenantService;
        $this->activationService = $activationService;
    }

    /**
     * Traite l'onboarding de manière asynchrone (nouveau flux)
     */
    public function processAsync(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email|max:255',
                'organization_name' => 'required|string|max:255',
            ]);

            $email = $validated['email'];
            $organizationName = $validated['organization_name'];

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
                    'message' => 'Votre espace Akasi Group a été créé avec succès. Veuillez consulter votre email pour finaliser votre inscription.',
                    'session_id' => $sessionId,
                    'result' => [
                        'subdomain' => $result['subdomain'],
                        'email' => $email,
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


}
