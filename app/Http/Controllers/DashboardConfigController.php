<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Tenant\ConfigurationDashboard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class DashboardConfigController extends Controller
{
    /**
     * Affiche la page de configuration du dashboard
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $config = ConfigurationDashboard::getOrCreateForUser($user->id);
        
        // Widgets disponibles
        $availableWidgets = $this->getAvailableWidgets();
        
        // Configuration actuelle des widgets
        $widgetsConfig = $config->widgets_config ?? [];
        
        return view('dashboard.config', [
            'config' => $config,
            'availableWidgets' => $availableWidgets,
            'widgetsConfig' => $widgetsConfig,
        ]);
    }

    /**
     * Sauvegarde la configuration du dashboard
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        
        $validator = Validator::make($request->all(), [
            'theme' => 'required|in:light,dark,auto',
            'langue' => 'required|in:fr,en,es',
            'widgets' => 'nullable|array',
            'widgets.*.id' => 'required|string',
            'widgets.*.enabled' => 'boolean',
            'widgets.*.position' => 'integer',
            'widgets.*.size' => 'in:small,medium,large',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $config = ConfigurationDashboard::getOrCreateForUser($user->id);
        
        // Préparer la configuration des widgets
        $widgetsConfig = [];
        if ($request->has('widgets') && is_array($request->widgets)) {
            foreach ($request->widgets as $widget) {
                // Vérifier si le widget est activé (checkbox checked)
                if (isset($widget['enabled']) && $widget['enabled'] == '1') {
                    $widgetsConfig[] = [
                        'id' => $widget['id'] ?? '',
                        'position' => isset($widget['position']) ? (int)$widget['position'] : 0,
                        'size' => $widget['size'] ?? 'medium',
                        'settings' => $widget['settings'] ?? [],
                    ];
                }
            }
            // Trier par position
            usort($widgetsConfig, function($a, $b) {
                return $a['position'] <=> $b['position'];
            });
        }

        // Sauvegarder la configuration
        $config->update([
            'theme' => $request->theme,
            'langue' => $request->langue,
            'widgets_config' => $widgetsConfig,
            'preferences' => $request->preferences ?? [],
        ]);

        return redirect()->route('dashboard')
            ->with('success', 'Configuration du dashboard sauvegardée avec succès.');
    }

    /**
     * Met à jour uniquement le thème
     */
    public function updateTheme(Request $request)
    {
        $user = Auth::user();
        $config = ConfigurationDashboard::getOrCreateForUser($user->id);
        
        $config->update([
            'theme' => $request->theme,
        ]);

        return response()->json([
            'success' => true,
            'theme' => $config->theme,
        ]);
    }

    /**
     * Retourne la liste des widgets disponibles
     */
    private function getAvailableWidgets(): array
    {
        return [
            [
                'id' => 'welcome',
                'name' => 'Message de bienvenue',
                'description' => 'Affiche un message personnalisé de bienvenue',
                'icon' => '👋',
                'default_size' => 'large',
            ],
            [
                'id' => 'tenant_info',
                'name' => 'Informations du tenant',
                'description' => 'Affiche les informations de l\'organisation',
                'icon' => '🏢',
                'default_size' => 'medium',
            ],
            [
                'id' => 'user_info',
                'name' => 'Informations utilisateur',
                'description' => 'Affiche les informations de l\'utilisateur connecté',
                'icon' => '👤',
                'default_size' => 'medium',
            ],
            [
                'id' => 'stats',
                'name' => 'Statistiques',
                'description' => 'Affiche des statistiques générales',
                'icon' => '📊',
                'default_size' => 'medium',
            ],
            [
                'id' => 'quick_actions',
                'name' => 'Actions rapides',
                'description' => 'Affiche des actions rapides fréquemment utilisées',
                'icon' => '⚡',
                'default_size' => 'small',
            ],
            [
                'id' => 'recent_activity',
                'name' => 'Activité récente',
                'description' => 'Affiche les activités récentes',
                'icon' => '🕐',
                'default_size' => 'medium',
            ],
        ];
    }
}
