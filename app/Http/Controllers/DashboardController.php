<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Tenant;
use App\Models\Tenant\ConfigurationDashboard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // Récupérer le sous-domaine depuis l'URL
        $host = $request->getHost();
        $parts = explode('.', $host);
        
        // En local, le format est: subdomain.localhost
        // En production, le format est: subdomain.domain.com
        if (count($parts) >= 2) {
            $subdomain = $parts[0];
            // Si c'est localhost, on a le sous-domaine
            if ($parts[1] === 'localhost' || (count($parts) >= 3 && $parts[1] !== 'www')) {
                // Le sous-domaine est le premier élément
            } else {
                // En production, extraire le sous-domaine
                $subdomain = $parts[0];
            }
        } else {
            // Fallback: essayer depuis le paramètre (pour compatibilité)
            $subdomain = $request->get('subdomain');
        }
        
        if (!$subdomain) {
            return redirect('/')->with('error', 'Sous-domaine non trouvé.');
        }
        
        // S'assurer qu'on utilise la base principale pour récupérer le tenant
        // Le modèle Tenant utilise toujours la connexion 'mysql'
        $tenant = Tenant::where('subdomain', $subdomain)
            ->where('status', 'active')
            ->first();
        
        if (!$tenant) {
            return redirect(subdomain_url($subdomain, '/welcome'))
                ->with('error', 'Tenant non trouvé ou inactif.');
        }
        
        // S'assurer que la base du tenant est bien basculée
        // Le middleware DetectTenant devrait déjà avoir fait cela
        try {
            $currentConnection = DB::connection()->getName();
            if ($currentConnection !== 'tenant') {
                // Basculer vers la base du tenant
                $tenantService = app(\App\Services\TenantService::class);
                $tenantService->switchToTenantDatabase($tenant->database_name);
                Config::set('auth.providers.users.model', \App\Models\Tenant\User::class);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Erreur lors du basculement vers la base tenant dans DashboardController: " . $e->getMessage());
        }
        
        // Vérifier que l'utilisateur est authentifié
        if (!Auth::check()) {
            return redirect(subdomain_url($subdomain, '/login'))
                ->with('error', 'Vous devez être connecté pour accéder au dashboard.');
        }

        // Charger la configuration du dashboard pour l'utilisateur connecté
        $user = Auth::user();
        
        if (!$user) {
            // Si l'utilisateur n'est pas trouvé, déconnecter et rediriger
            Auth::logout();
            return redirect(subdomain_url($subdomain, '/login'))
                ->with('error', 'Session expirée. Veuillez vous reconnecter.');
        }
        
        $dashboardConfig = ConfigurationDashboard::getOrCreateForUser($user->id);
        
        // Récupérer les widgets configurés
        $widgetsConfig = $dashboardConfig->widgets_config ?? [];
        
        // Si aucun widget n'est configuré, utiliser la configuration par défaut
        if (empty($widgetsConfig)) {
            $widgetsConfig = $this->getDefaultWidgetsConfig();
        }
        
        return view('dashboard', [
            'tenant' => $tenant,
            'subdomain' => $subdomain,
            'dashboardConfig' => $dashboardConfig,
            'widgetsConfig' => $widgetsConfig,
        ]);
    }

    /**
     * Retourne la configuration par défaut des widgets
     */
    private function getDefaultWidgetsConfig(): array
    {
        return [
            ['id' => 'welcome', 'position' => 0, 'size' => 'large', 'settings' => []],
            ['id' => 'tenant_info', 'position' => 1, 'size' => 'medium', 'settings' => []],
            ['id' => 'user_info', 'position' => 2, 'size' => 'medium', 'settings' => []],
            ['id' => 'stats', 'position' => 3, 'size' => 'medium', 'settings' => []],
        ];
    }
}
