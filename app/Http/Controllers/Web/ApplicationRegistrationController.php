<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Services\DatabaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class ApplicationRegistrationController extends Controller
{
    protected $databaseService;

    public function __construct(DatabaseService $databaseService)
    {
        $this->databaseService = $databaseService;
    }

    /**
     * Affiche le formulaire d'enregistrement d'application
     */
    public function showRegisterForm()
    {
        return view('applications.register');
    }

    /**
     * Affiche la liste des applications (recherche par email)
     */
    public function index(Request $request)
    {
        $email = $request->query('email');
        $applications = collect();

        if ($email) {
            $applications = Application::where('contact_email', $email)
                ->where('is_active', true)
                ->orderBy('created_at', 'desc')
                ->get();
        }

        return view('applications.index', [
            'applications' => $applications,
            'searchEmail' => $email,
        ]);
    }

    /**
     * Traite l'enregistrement d'application
     */
    public function register(Request $request)
    {
        // Rate limiting
        $key = 'application_register_web:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors([
                'error' => 'Trop de tentatives. Veuillez réessayer dans quelques minutes.'
            ])->withInput();
        }
        RateLimiter::hit($key, 3600);

        $validated = $request->validate([
            'app_name' => 'required|string|max:50|alpha_dash|unique:applications,app_name',
            'display_name' => 'required|string|max:255',
            'contact_email' => 'required|email|max:255',
            'website' => 'nullable|url|max:255',
        ]);

        // Vérifier les noms réservés
        $reservedNames = ['admin', 'api', 'www', 'mail', 'ftp', 'localhost', 'test', 'dev', 'staging', 'prod'];
        if (in_array(strtolower($validated['app_name']), $reservedNames)) {
            return back()->withErrors([
                'app_name' => 'Ce nom d\'application est réservé. Veuillez en choisir un autre.'
            ])->withInput();
        }

        try {
            // Enregistrer l'application
            $result = Application::register(
                $validated['app_name'],
                $validated['display_name'],
                $validated['contact_email'],
                $validated['website'] ?? null
            );

            // Créer la base de données
            $databaseCreated = false;
            $dbResult = null;
            
            try {
                $dbResult = $this->databaseService->createApplicationDatabase(
                    $result['id'],
                    $validated['app_name']
                );
                $databaseCreated = true;
            } catch (\Exception $dbException) {
                Log::warning('Échec de la création de la base de données', [
                    'app_id' => $result['app_id'],
                    'error' => $dbException->getMessage(),
                ]);
            }

            // Envoyer la master key par email
            try {
                Mail::send('emails.application-registered', [
                    'app_name' => $result['app_name'],
                    'display_name' => $result['display_name'],
                    'master_key' => $result['master_key'],
                    'database_created' => $databaseCreated,
                    'database' => $databaseCreated ? [
                        'name' => $dbResult['database']->database_name,
                        'host' => $dbResult['database']->db_host,
                        'port' => $dbResult['database']->db_port,
                        'username' => $dbResult['database']->db_username,
                        'password' => $dbResult['plain_password'],
                    ] : null,
                ], function ($message) use ($validated) {
                    $message->to($validated['contact_email'])
                        ->subject('Votre application a été enregistrée - Master Key');
                });
            } catch (\Exception $mailException) {
                Log::warning('Échec de l\'envoi de l\'email', [
                    'app_id' => $result['app_id'],
                    'error' => $mailException->getMessage(),
                ]);
            }

            // Stocker temporairement la master key en session pour l'afficher une seule fois
            session([
                'new_application' => [
                    'app_id' => $result['app_id'],
                    'app_name' => $result['app_name'],
                    'display_name' => $result['display_name'],
                    'master_key' => $result['master_key'],
                    'database_created' => $databaseCreated,
                    'database' => $databaseCreated ? [
                        'name' => $dbResult['database']->database_name,
                        'host' => $dbResult['database']->db_host,
                        'port' => $dbResult['database']->db_port,
                        'username' => $dbResult['database']->db_username,
                        'password' => $dbResult['plain_password'],
                    ] : null,
                ]
            ]);

            return redirect()->route('applications.dashboard', ['app_id' => $result['app_id']])
                ->with('success', 'Application enregistrée avec succès !');
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'enregistrement d\'application: ' . $e->getMessage());
            
            return back()->withErrors([
                'error' => 'Une erreur est survenue lors de l\'enregistrement. Veuillez réessayer plus tard.'
            ])->withInput();
        }
    }

    /**
     * Affiche le dashboard d'une application
     */
    public function dashboard(Request $request, string $appId)
    {
        // Pour l'instant, on utilise la master key depuis la session ou on demande à l'utilisateur
        // Dans un vrai système, on aurait une authentification dédiée
        
        $application = Application::where('app_id', $appId)->first();
        
        if (!$application) {
            return redirect()->route('applications.index')
                ->with('error', 'Application non trouvée. Veuillez rechercher votre application par email.');
        }

        // Récupérer les onboardings de cette application
        $onboardings = \App\Models\OnboardingRegistration::where('application_id', $application->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Statistiques
        $stats = [
            'total' => \App\Models\OnboardingRegistration::where('application_id', $application->id)->count(),
            'pending' => \App\Models\OnboardingRegistration::where('application_id', $application->id)
                ->where('status', 'pending')->count(),
            'activated' => \App\Models\OnboardingRegistration::where('application_id', $application->id)
                ->where('status', 'activated')->count(),
            'cancelled' => \App\Models\OnboardingRegistration::where('application_id', $application->id)
                ->where('status', 'cancelled')->count(),
        ];

        // Récupérer les clés API
        $apiKeys = \App\Models\ApiKey::where('application_id', $application->id)
            ->orderBy('created_at', 'desc')
            ->get();

        // Récupérer la master key depuis la session si disponible (affichage unique)
        $newApplication = session('new_application');
        $masterKey = null;
        if ($newApplication && $newApplication['app_id'] === $appId) {
            $masterKey = $newApplication['master_key'];
            // Supprimer de la session après affichage
            session()->forget('new_application');
        }

        return view('applications.dashboard', [
            'application' => $application,
            'onboardings' => $onboardings,
            'stats' => $stats,
            'apiKeys' => $apiKeys,
            'masterKey' => $masterKey,
            'database' => $newApplication['database'] ?? null,
        ]);
    }

    /**
     * Affiche la page de gestion des clés API
     */
    public function apiKeys(Request $request, string $appId)
    {
        $application = Application::where('app_id', $appId)->first();
        
        if (!$application) {
            return redirect()->route('applications.index')
                ->with('error', 'Application non trouvée. Veuillez rechercher votre application par email.');
        }

        $apiKeys = \App\Models\ApiKey::where('application_id', $application->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('applications.api-keys', [
            'application' => $application,
            'apiKeys' => $apiKeys,
        ]);
    }
}
