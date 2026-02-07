<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OnboardingSession;
use App\Services\DashboardService;
use App\Services\ActivationService;
use App\Mail\ActivationMail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class DashboardController extends Controller
{
    protected $dashboardService;
    protected $activationService;

    public function __construct(DashboardService $dashboardService, ActivationService $activationService)
    {
        $this->dashboardService = $dashboardService;
        $this->activationService = $activationService;
    }

    public function index(Request $request)
    {
        // IMPORTANT: Cette route est protégée par le middleware 'auth'
        // Donc Auth::check() devrait toujours être true ici
        
        // Récupérer le sous-domaine depuis le host
        $host = $request->getHost();
        $parts = explode('.', $host);
        
        // En local: format subdomain.localhost
        // En production: format subdomain.domain.tld
        if (config('app.env') === 'local' && count($parts) >= 2 && $parts[1] === 'localhost') {
            $subdomain = $parts[0];
        } elseif (count($parts) >= 3) {
            $subdomain = $parts[0];
        } else {
            $subdomain = null;
        }
        
        // Si pas de sous-domaine, utiliser celui de la session
        if (!$subdomain) {
            $subdomain = session('current_subdomain');
        }
        
        // Si toujours pas de sous-domaine, utiliser les données de session
        if (!$subdomain) {
            $onboardingResult = session('onboarding_result', []);
            $subdomain = $onboardingResult['subdomain'] ?? null;
        }
        
        // Si toujours pas de sous-domaine, essayer de le récupérer depuis l'URL complète
        if (!$subdomain) {
            // Essayer d'extraire depuis l'URL complète
            $fullUrl = $request->fullUrl();
            if (preg_match('/https?:\/\/([^\.]+)\.localhost/', $fullUrl, $matches)) {
                $subdomain = $matches[1];
            } elseif (preg_match('/https?:\/\/([^\.]+)\.([^\/]+)/', $fullUrl, $matches)) {
                // Pour la production, extraire le sous-domaine
                $subdomain = $matches[1];
            }
        }
        
        // Si toujours pas de sous-domaine, utiliser le host comme fallback
        if (!$subdomain && $host) {
            // Essayer d'extraire le sous-domaine depuis le host
            $hostParts = explode('.', $host);
            if (count($hostParts) > 0 && $hostParts[0] !== 'localhost' && $hostParts[0] !== '127.0.0.1') {
                $subdomain = $hostParts[0];
            }
        }
        
        // Si toujours pas de sous-domaine, afficher le dashboard avec un message d'erreur
        if (!$subdomain) {
            \Illuminate\Support\Facades\Log::warning('Dashboard - Sous-domaine non trouvé', [
                'host' => $host,
                'url' => $request->fullUrl()
            ]);
            
            // Créer un objet onboarding minimal pour éviter les erreurs
            $onboarding = (object) [
                'hospital_name' => 'Hôpital',
                'subdomain' => $host, // Utiliser le host comme fallback
            ];
            
            return view('dashboard', [
                'onboarding' => $onboarding,
                'subdomain' => $host // Utiliser le host comme fallback au lieu de null
            ]);
        }
        
        // IMPORTANT: On est sur une route protégée par 'auth', donc l'utilisateur est authentifié
        // Mais on doit basculer vers la base du tenant APRÈS avoir vérifié l'authentification
        
        // D'abord, récupérer l'onboarding depuis la base principale
        // (OnboardingSession est stocké dans la base principale)
        Config::set('database.default', 'mysql');
        DB::purge('tenant');
        
        $onboarding = OnboardingSession::on('mysql')
            ->where('subdomain', $subdomain)
            ->where('status', 'completed')
            ->first();
        
        // Maintenant, basculer vers la base du tenant pour que l'utilisateur soit trouvé
        $tenantService = app(\App\Services\TenantService::class);
        $databaseName = $tenantService->getTenantDatabase($subdomain);
        
        if ($databaseName) {
            try {
                // Vérifier si on est déjà sur la bonne base
                $currentDatabase = DB::connection()->getDatabaseName();
                
                if ($currentDatabase !== $databaseName) {
                    // Sauvegarder l'ID de l'utilisateur avant le basculement
                    $userId = Auth::id();
                    
                    $tenantService->switchToTenantDatabase($databaseName);
                    session(['current_subdomain' => $subdomain]);
                    
                    // Reconnecter l'utilisateur après le basculement
                    if ($userId) {
                        $user = \App\Models\User::find($userId);
                        if ($user) {
                            Auth::login($user, true);
                            \Illuminate\Support\Facades\Log::info("Utilisateur reconnecté après basculement vers la base tenant", [
                                'user_id' => $userId,
                                'database' => $databaseName
                            ]);
                        }
                    }
                } else {
                    // On est déjà sur la bonne base, juste s'assurer que l'utilisateur est connecté
                    if (!Auth::check() && Auth::id()) {
                        $user = \App\Models\User::find(Auth::id());
                        if ($user) {
                            Auth::login($user, true);
                        }
                    }
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Erreur lors du basculement vers la base tenant dans DashboardController: " . $e->getMessage());
            }
        }
        
        // Si pas d'onboarding trouvé, créer un objet onboarding avec les données disponibles
        // IMPORTANT: Ne JAMAIS rediriger vers welcome depuis le dashboard si l'utilisateur est connecté
        // car cela crée une boucle infinie (welcome redirige vers dashboard)
        if (!$onboarding) {
            $user = Auth::check() ? Auth::user() : null;
            $onboardingResult = session('onboarding_result', []);
            
            // Récupérer les données depuis la session d'onboarding si disponible
            $onboardingData = session('onboarding_data', []);
            
            $onboarding = (object) [
                'hospital_name' => $onboardingResult['hospital_name'] 
                    ?? $onboardingData['step1']['hospital_name'] 
                    ?? 'Hôpital',
                'hospital_address' => $onboardingResult['hospital_address'] 
                    ?? $onboardingData['step1']['hospital_address'] 
                    ?? null,
                'hospital_phone' => $onboardingResult['hospital_phone'] 
                    ?? $onboardingData['step1']['hospital_phone'] 
                    ?? null,
                'hospital_email' => $onboardingResult['hospital_email'] 
                    ?? $onboardingData['step1']['hospital_email'] 
                    ?? null,
                'admin_first_name' => $onboardingResult['admin_first_name'] 
                    ?? $onboardingData['step2']['admin_first_name'] 
                    ?? ($user ? (explode(' ', $user->name)[0] ?? '') : ''),
                'admin_last_name' => $onboardingResult['admin_last_name'] 
                    ?? $onboardingData['step2']['admin_last_name'] 
                    ?? ($user ? (explode(' ', $user->name)[1] ?? '') : ''),
                'admin_email' => $user ? $user->email : ($onboardingResult['admin_email'] ?? $onboardingData['step2']['admin_email'] ?? ''),
                'subdomain' => $subdomain,
            ];
        }
        
        // Récupérer les statistiques et données pour le dashboard
        $stats = $this->dashboardService->getStats();
        $activities = $this->dashboardService->getRecentActivities(5);
        $unreadCount = 0;
        if (Auth::check()) {
            $user = Auth::user();
            $unreadCount = \App\Models\Notification::where('user_id', $user->id)->whereNull('read_at')->count();
        }
        
        // Log pour le débogage
        \Illuminate\Support\Facades\Log::info('Dashboard - Données envoyées à la vue', [
            'subdomain' => $subdomain,
            'onboarding_exists' => $onboarding !== null,
            'user_authenticated' => Auth::check(),
            'user_email' => Auth::check() ? Auth::user()->email : null,
            'current_database' => \Illuminate\Support\Facades\DB::connection()->getDatabaseName(),
        ]);
        
        return view('dashboard.index', [
            'onboarding' => $onboarding,
            'subdomain' => $subdomain,
            'stats' => $stats,
            'activities' => $activities,
            'unreadCount' => $unreadCount,
        ]);
    }

    /**
     * Renvoie l'email d'activation pour l'utilisateur connecté
     */
    public function resendActivationEmail(Request $request)
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non authentifié'
            ], 401);
        }

        $user = Auth::user();
        $email = $user->email;

        // Récupérer le sous-domaine depuis le host
        $host = $request->getHost();
        $parts = explode('.', $host);
        
        if (config('app.env') === 'local' && count($parts) >= 2 && $parts[1] === 'localhost') {
            $subdomain = $parts[0];
        } elseif (count($parts) >= 3) {
            $subdomain = $parts[0];
        } else {
            $subdomain = session('current_subdomain');
        }

        if (!$subdomain) {
            return response()->json([
                'success' => false,
                'message' => 'Sous-domaine non trouvé'
            ], 400);
        }

        try {
            // Récupérer l'organisation depuis l'onboarding
            Config::set('database.default', 'mysql');
            DB::purge('tenant');
            
            $onboarding = OnboardingSession::on('mysql')
                ->where('subdomain', $subdomain)
                ->where('status', 'completed')
                ->first();

            $organizationName = $onboarding->organization_name ?? $onboarding->hospital_name ?? config('app.brand_name');

            // Créer un nouveau token d'activation
            $activationToken = $this->activationService->createActivationToken(
                $email,
                $organizationName,
                $subdomain,
                $onboarding->database_name ?? null
            );

            // Envoyer l'email
            Mail::to($email)->send(
                new ActivationMail($email, $organizationName, $activationToken)
            );

            Log::info('Email d\'activation renvoyé', [
                'email' => $email,
                'subdomain' => $subdomain,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Email d\'activation renvoyé avec succès'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors du renvoi de l\'email d\'activation: ' . $e->getMessage(), [
                'email' => $email,
                'subdomain' => $subdomain,
                'exception' => get_class($e),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi de l\'email: ' . $e->getMessage()
            ], 500);
        }
    }
}
