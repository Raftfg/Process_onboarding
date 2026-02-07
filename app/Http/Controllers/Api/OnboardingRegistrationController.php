<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OnboardingRegistrationService;
use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OnboardingRegistrationController extends Controller
{
    protected $onboardingRegistrationService;

    public function __construct(OnboardingRegistrationService $onboardingRegistrationService)
    {
        $this->onboardingRegistrationService = $onboardingRegistrationService;
    }

    /**
     * Enregistre un nouvel onboarding
     * 
     * POST /api/v1/onboarding/register
     */
    public function register(Request $request)
    {
        try {
            // Récupérer l'application depuis le middleware
            $application = $request->get('application');

            if (!$application) {
                return response()->json([
                    'success' => false,
                    'message' => 'Application non trouvée. Vérifiez votre master_key.'
                ], 401);
            }

            // Validation
            $validated = $request->validate([
                'email' => 'required|email|max:255',
                'organization_name' => 'nullable|string|max:255',
                'metadata' => 'nullable|array',
                'generate_api_key' => 'nullable|boolean',
            ]);

            // Enregistrer l'onboarding
            $result = $this->onboardingRegistrationService->registerOnboarding(
                $application,
                $validated['email'],
                $validated['organization_name'] ?? null,
                $validated['metadata'] ?? [],
                $validated['generate_api_key'] ?? false
            );

            return response()->json([
                'success' => true,
                'message' => 'Onboarding enregistré avec succès',
                'onboarding' => $result,
                'warnings' => $result['api_key'] ? [
                    '⚠️ IMPORTANT: Sauvegardez la clé API immédiatement ! Elle ne sera plus jamais affichée.',
                ] : [],
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'enregistrement d\'onboarding: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'enregistrement: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupère les détails d'un onboarding
     * 
     * GET /api/v1/onboarding/{uuid}
     */
    public function show(Request $request, string $uuid)
    {
        try {
            $application = $request->get('application');

            $registration = $this->onboardingRegistrationService->getByUuid($uuid);

            if (!$registration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Onboarding introuvable.'
                ], 404);
            }

            // Vérifier que l'onboarding appartient à l'application
            if ($registration->application_id !== $application->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'avez pas l\'autorisation d\'accéder à cet onboarding.'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'onboarding' => [
                    'uuid' => $registration->uuid,
                    'subdomain' => $registration->subdomain,
                    'email' => $registration->email,
                    'organization_name' => $registration->organization_name,
                    'status' => $registration->status,
                    'dns_configured' => $registration->dns_configured,
                    'ssl_configured' => $registration->ssl_configured,
                    'url' => app(\App\Services\SubdomainService::class)->getSubdomainUrl($registration->subdomain),
                    'created_at' => $registration->created_at->toIso8601String(),
                    'updated_at' => $registration->updated_at->toIso8601String(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération d\'onboarding: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue.'
            ], 500);
        }
    }

    /**
     * Met à jour le statut d'un onboarding
     * 
     * PUT /api/v1/onboarding/{uuid}/status
     */
    public function updateStatus(Request $request, string $uuid)
    {
        try {
            $application = $request->get('application');

            $registration = $this->onboardingRegistrationService->getByUuid($uuid);

            if (!$registration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Onboarding introuvable.'
                ], 404);
            }

            // Vérifier que l'onboarding appartient à l'application
            if ($registration->application_id !== $application->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'avez pas l\'autorisation de modifier cet onboarding.'
                ], 403);
            }

            $validated = $request->validate([
                'status' => 'required|in:pending,activated,cancelled,completed',
            ]);

            $this->onboardingRegistrationService->updateStatus($registration, $validated['status']);

            return response()->json([
                'success' => true,
                'message' => 'Statut mis à jour avec succès',
                'onboarding' => [
                    'uuid' => $registration->uuid,
                    'status' => $registration->fresh()->status,
                ],
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise à jour du statut: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Liste tous les onboardings d'une application
     * 
     * GET /api/v1/onboarding
     */
    public function index(Request $request)
    {
        try {
            $application = $request->get('application');

            $registrations = $application->onboardingRegistrations()
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($registration) {
                    return [
                        'uuid' => $registration->uuid,
                        'subdomain' => $registration->subdomain,
                        'email' => $registration->email,
                        'organization_name' => $registration->organization_name,
                        'status' => $registration->status,
                        'dns_configured' => $registration->dns_configured,
                        'ssl_configured' => $registration->ssl_configured,
                        'created_at' => $registration->created_at->toIso8601String(),
                    ];
                });

            return response()->json([
                'success' => true,
                'onboardings' => $registrations,
                'count' => $registrations->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des onboardings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue.'
            ], 500);
        }
    }
}
