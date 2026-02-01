<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\TenantCustomizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class CustomizationController extends Controller
{
    protected $customizationService;

    public function __construct(TenantCustomizationService $customizationService)
    {
        $this->customizationService = $customizationService;
    }

    /**
     * Afficher la page de personnalisation
     */
    public function index()
    {
        $branding = $this->customizationService->getBranding();
        $layout = $this->customizationService->getLayout();
        $menu = $this->customizationService->getMenu();

        return view('dashboard.customization.index', [
            'branding' => $branding,
            'layout' => $layout,
            'menu' => $menu,
        ]);
    }

    /**
     * Mettre à jour le branding
     */
    public function updateBranding(Request $request)
    {
        try {
            
            // Vérifier et créer la table tenant_settings si elle n'existe pas
            $this->ensureTenantSettingsTableExists();
            
            $validated = $request->validate([
                'primary_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
                'secondary_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
                'accent_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
                'background_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
                'organization_name' => 'nullable|string|max:255',
            ]);

            foreach ($validated as $key => $value) {
                if ($value !== null) {
                    $this->customizationService->setSetting($key, $value, 'branding');
                }
            }

            // Invalider le cache de manière exhaustive pour cette base
            $connection = \Illuminate\Support\Facades\DB::connection();
            $databaseName = $connection->getDatabaseName();
            
            // Vider tous les caches liés au branding
            \Illuminate\Support\Facades\Cache::forget("tenant_settings_group_branding_{$databaseName}");
            \Illuminate\Support\Facades\Cache::forget("tenant_settings_all_{$databaseName}");
            
            foreach (['primary_color', 'secondary_color', 'accent_color', 'background_color', 'organization_name', 'logo_url', 'favicon_url'] as $key) {
                \Illuminate\Support\Facades\Cache::forget("tenant_setting_{$key}_{$databaseName}");
            }
            
            \App\Models\TenantSetting::clearCache();

            return redirect()->route('dashboard.customization')
                ->with('success', 'Branding mis à jour avec succès. Les changements sont appliqués immédiatement.');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Erreur dans updateBranding: " . $e->getMessage());
            $userMessage = \App\Helpers\ErrorFormatter::formatException($e);
            return back()->with('error', $userMessage);
        }
    }

    /**
     * S'assurer que la table tenant_settings existe
     */
    protected function ensureTenantSettingsTableExists(): void
    {
        try {
            $connection = \Illuminate\Support\Facades\DB::connection();
            $connectionName = $connection->getName();
            
            if (!\Illuminate\Support\Facades\Schema::connection($connectionName)->hasTable('tenant_settings')) {
                
                \Illuminate\Support\Facades\Schema::connection($connectionName)->create('tenant_settings', function ($table) {
                    $table->id();
                    $table->string('key')->unique();
                    $table->json('value');
                    $table->string('group')->default('general')->index();
                    $table->timestamps();
                    $table->index(['group', 'key']);
                });
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Erreur lors de la création de la table tenant_settings: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Mettre à jour le layout
     */
    public function updateLayout(Request $request)
    {
        $validated = $request->validate([
            'welcome_message' => 'nullable|string|max:500',
            'dashboard_widgets' => 'nullable|array',
            'dashboard_widgets.stats' => 'boolean',
            'dashboard_widgets.activities' => 'boolean',
            'dashboard_widgets.calendar' => 'boolean',
            'dashboard_widgets.quick_actions' => 'boolean',
            'grid_columns' => 'nullable|integer|min:1|max:6',
            'spacing' => 'nullable|in:compact,normal,comfortable',
        ]);

        if (isset($validated['welcome_message'])) {
            $this->customizationService->setSetting('welcome_message', $validated['welcome_message'], 'layout');
        }

        if (isset($validated['dashboard_widgets'])) {
            $currentWidgets = $this->customizationService->getLayout()['dashboard_widgets'];
            $updatedWidgets = array_merge($currentWidgets, $validated['dashboard_widgets']);
            $this->customizationService->setSetting('dashboard_widgets', $updatedWidgets, 'layout');
        }

        if (isset($validated['grid_columns'])) {
            $this->customizationService->setSetting('grid_columns', $validated['grid_columns'], 'layout');
        }

        if (isset($validated['spacing'])) {
            $this->customizationService->setSetting('spacing', $validated['spacing'], 'layout');
        }

        \App\Models\TenantSetting::clearCache();

        return redirect()->route('dashboard.customization')
            ->with('success', 'Layout mis à jour avec succès');
    }

    /**
     * Mettre à jour le menu
     */
    public function updateMenu(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.key' => 'required|string',
            'items.*.label' => 'required|string|max:255',
            'items.*.icon' => 'nullable|string|max:10',
            'items.*.enabled' => 'boolean',
            'items.*.order' => 'required|integer|min:1',
        ]);

        // Trier par ordre
        usort($validated['items'], function ($a, $b) {
            return $a['order'] <=> $b['order'];
        });

        $this->customizationService->setSetting('items', $validated['items'], 'menu');
        \App\Models\TenantSetting::clearCache();

        return redirect()->route('dashboard.customization')
            ->with('success', 'Menu mis à jour avec succès');
    }

    /**
     * Upload du logo
     */
    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $subdomain = Session::get('current_subdomain');
        
        // Fallback: extraire le sous-domaine depuis l'hôte si absent de la session
        if (!$subdomain) {
            $host = $request->getHost();
            $parts = explode('.', $host);
            if (config('app.env') === 'local') {
                if (count($parts) >= 2 && $parts[1] === 'localhost') {
                    $subdomain = $parts[0];
                }
            } else {
                if (count($parts) >= 3) {
                    $subdomain = $parts[0];
                }
            }
            if ($subdomain) {
                Session::put('current_subdomain', $subdomain);
            }
        }
        
        if (!$subdomain) {
            return back()->with('error', 'Impossible de déterminer le sous-domaine');
        }

        if (!$request->hasFile('logo')) {
            return back()->with('error', 'Aucun fichier n\'a été uploadé');
        }

        try {
            $url = $this->customizationService->uploadLogo($request->file('logo'), $subdomain);
            
            // Vider le cache de manière agressive
            try {
                $connection = \Illuminate\Support\Facades\DB::connection();
                $databaseName = $connection->getDatabaseName();
                
                // Vider tous les caches liés au branding
                \Illuminate\Support\Facades\Cache::forget("tenant_settings_group_branding_{$databaseName}");
                \Illuminate\Support\Facades\Cache::forget("tenant_setting_logo_url_{$databaseName}");
                \Illuminate\Support\Facades\Cache::forget("tenant_settings_all_{$databaseName}");
                
                \Illuminate\Support\Facades\Cache::forget("tenant_settings_all_{$databaseName}");
            } catch (\Exception $cacheException) {
                // Ignore cache errors
            }
            
            // Vider aussi le cache global
            \App\Models\TenantSetting::clearCache();

            return redirect()->route('dashboard.customization')
                ->with('success', 'Logo uploadé avec succès. URL: ' . $url);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Erreur lors de l'upload du logo: " . $e->getMessage());
            return back()->with('error', 'Erreur lors de l\'upload du logo: ' . $e->getMessage());
        }
    }

    /**
     * Réinitialiser aux valeurs par défaut
     */
    public function reset()
    {
        // Supprimer tous les settings personnalisés
        \App\Models\TenantSetting::truncate();
        
        // Réinitialiser avec les valeurs par défaut
        $this->customizationService->initializeDefaults();
        \App\Models\TenantSetting::clearCache();

        return redirect()->route('dashboard.customization')
            ->with('success', 'Personnalisation réinitialisée aux valeurs par défaut');
    }

    /**
     * Prévisualiser les changements (AJAX)
     */
    public function preview(Request $request)
    {
        $type = $request->input('type');
        $data = $request->except(['type', '_token']);

        // Simuler les changements pour la prévisualisation
        $preview = [];
        
        if ($type === 'branding') {
            $preview = [
                'primary_color' => $data['primary_color'] ?? '#667eea',
                'secondary_color' => $data['secondary_color'] ?? '#764ba2',
                'accent_color' => $data['accent_color'] ?? '#10b981',
            ];
        }

        return response()->json([
            'success' => true,
            'preview' => $preview,
        ]);
    }
}
