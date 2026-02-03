<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use App\Models\OnboardingSession;

class TestModuleController extends Controller
{
    public function index()
    {
        $tenants = DB::table('test_client_tenants')->orderBy('created_at', 'desc')->get();
        return view('modules.test.index', compact('tenants'));
    }

    public function trigger(Request $request)
    {
        $request->validate([
            'organization_name' => 'required|string',
            'email' => 'required|email',
            'app_name' => 'required|string',
        ]);

        $payload = [
            'email' => $request->email,
            'organization_name' => $request->organization_name,
            'callback_url' => route('module.test.callback'),
            'migrations' => [
                [
                    'filename' => '2024_02_01_create_test_table.php',
                    'content' => "<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('test_module_data', function (Blueprint \$table) {
            \$table->id();
            \$table->string('test_field');
            \$table->timestamps();
        });
    }
};"
                ]
            ]
        ];

        try {
            Log::info("[TestModule] Déclenchement onboarding pour {$request->organization_name} (App: {$request->app_name})");

            $executionPayload = $payload;
            
            // Si on est en local, on simule l'appel interne pour éviter les deadlocks
            if (app()->environment('local')) {
                Log::info("[TestModule] Mode LOCAL détecté - Simulation via internal request et mock Webhook");
                
                // 1. Mocker le WebhookService pour éviter que l'API n'essaie d'envoyer un webhook HTTP vers localhost
                $webhookMock = new class extends \App\Services\WebhookService {
                    public function trigger(string $event, array $data): void {
                        Log::info("[TestModule] Webhook mocked for event: $event (No HTTP request sent)");
                    }
                };
                app()->instance(\App\Services\WebhookService::class, $webhookMock);

                // 2. Créer une instance de Request manuelle
                $internalRequest = Request::create(
                    '/api/v1/onboarding/external',
                    'POST',
                    [], // parameters
                    [], // cookies
                    [], // files
                    [], // server
                    json_encode($executionPayload)
                );
                $internalRequest->headers->set('X-App-Name', $request->app_name);
                $internalRequest->headers->set('Content-Type', 'application/json');
                $internalRequest->headers->set('Accept', 'application/json');

                // 3. Appeler le contrôleur directement
                $apiController = app(\App\Http\Controllers\Api\OnboardingApiController::class);
                $response = $apiController->externalStore($internalRequest);
                
                $responseData = json_decode($response->getContent(), true);
            } else {
                // Appel HTTP standard pour les environnements non-locaux
                $apiUrl = url('/api/v1/onboarding/external');
                $response = Http::withHeaders(['X-App-Name' => $request->app_name])
                    ->post($apiUrl, $executionPayload);
                
                $responseData = $response->json();
            }

            if (isset($responseData['success']) && $responseData['success']) {
                $result = $responseData['result'] ?? [];
                
                // En local, on simule le succès immédiat (puisque le webhook est mocké)
                DB::table('test_client_tenants')->insert([
                    'organization_name' => $request->organization_name,
                    'admin_email' => $request->email,
                    'status' => app()->environment('local') ? 'active' : 'pending',
                    'database_name' => $result['database'] ?? null,
                    'domain_url' => $result['url'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return redirect()->route('module.test.index')->with('success', 'Onboarding initié avec succès !');
            } else {
                $errorMsg = $responseData['message'] ?? 'Erreur inconnue';
                if (!empty($responseData['errors'])) {
                    $errorMsg .= ' : ' . json_encode($responseData['errors']);
                }
                return back()->with('error', 'API Error: ' . $errorMsg);
            }

        } catch (\Exception $e) {
            Log::error("[TestModule] Exception: " . $e->getMessage());
            return back()->with('error', 'Exception: ' . $e->getMessage());
        }
    }

    public function callback(Request $request)
    {
        Log::info("[TestModule] Webhook reçu", $request->all());

        $organizationName = $request->input('organization_name') ?? $request->input('data.organization_name');
        
        if ($organizationName) {
            DB::table('test_client_tenants')
                ->where('organization_name', $organizationName)
                ->update([
                    'status' => 'active',
                    'updated_at' => now()
                ]);
            return response()->json(['status' => 'ok']);
        }

        return response()->json(['status' => 'error', 'message' => 'Organization not found'], 404);
    }
}
