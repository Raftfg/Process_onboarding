<?php

namespace App\Services;

use App\Models\TenantSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class TenantCustomizationService
{
    protected $subdomain;

    public function __construct()
    {
        // Récupérer le subdomain depuis la session ou la requête
        $this->subdomain = session('current_subdomain');
    }

    /**
     * Récupérer tous les settings
     */
    public function getSettings(): array
    {
        return TenantSetting::getAll();
    }

    /**
     * Récupérer un setting spécifique
     */
    public function getSetting(string $key, $default = null)
    {
        return TenantSetting::get($key, $default);
    }

    /**
     * Définir un setting
     */
    public function setSetting(string $key, $value, string $group = 'general'): void
    {
        TenantSetting::set($key, $value, $group);
    }

    /**
     * Récupérer les settings de branding
     */
    public function getBranding(): array
    {
        $defaults = [
            'primary_color' => '#00286f',
            'secondary_color' => '#001d4d',
            'accent_color' => '#10b981',
            'background_color' => '#f5f7fa',
            'logo_url' => null,
            'organization_name' => null,
            'favicon_url' => null,
        ];

        try {
            $connection = \Illuminate\Support\Facades\DB::connection();
            $databaseName = $connection->getDatabaseName();
            
            // Vérifier que la table existe
            if (!\Illuminate\Support\Facades\Schema::hasTable('tenant_settings')) {
                \Illuminate\Support\Facades\Log::info("Table tenant_settings n'existe pas, utilisation des valeurs par défaut");
                return $defaults;
            }
            
            $settings = TenantSetting::getGroup('branding');
            
            \Illuminate\Support\Facades\Log::info("Settings branding récupérés", [
                'database' => $databaseName,
                'settings' => $settings,
                'merged' => array_merge($defaults, $settings)
            ]);
            
            $result = array_merge($defaults, $settings);
            
            // S'assurer que les couleurs sont bien des chaînes
            foreach (['primary_color', 'secondary_color', 'accent_color', 'background_color'] as $key) {
                if (isset($result[$key]) && !is_string($result[$key])) {
                    $result[$key] = (string) $result[$key];
                }
            }
            
            return $result;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Erreur lors de la récupération du branding: " . $e->getMessage());
            return $defaults;
        }
    }

    /**
     * Récupérer les settings de layout
     */
    public function getLayout(): array
    {
        $defaults = [
            'welcome_message' => 'Bienvenue sur votre espace',
            'dashboard_widgets' => [
                'stats' => true,
                'activities' => true,
                'calendar' => true,
                'quick_actions' => true,
            ],
            'grid_columns' => 3,
            'spacing' => 'normal',
        ];

        $settings = TenantSetting::getGroup('layout');
        
        // Merge récursif pour les widgets
        if (isset($settings['dashboard_widgets'])) {
            $defaults['dashboard_widgets'] = array_merge(
                $defaults['dashboard_widgets'],
                $settings['dashboard_widgets']
            );
        }
        
        return array_merge($defaults, $settings);
    }

    /**
     * Récupérer la configuration du menu
     */
    public function getMenu(): array
    {
        $defaults = [
            'items' => [
                ['key' => 'dashboard', 'label' => 'Tableau de bord', 'icon' => '📊', 'enabled' => true, 'order' => 1],
                ['key' => 'users', 'label' => 'Utilisateurs', 'icon' => '👥', 'enabled' => true, 'order' => 2],
                ['key' => 'activities', 'label' => 'Activités', 'icon' => '📝', 'enabled' => true, 'order' => 3],
                ['key' => 'reports', 'label' => 'Rapports', 'icon' => '📈', 'enabled' => true, 'order' => 4],
                ['key' => 'settings', 'label' => 'Paramètres', 'icon' => '⚙️', 'enabled' => true, 'order' => 5],
                ['key' => 'customization', 'label' => 'Personnalisation', 'icon' => '🎨', 'enabled' => true, 'order' => 6],
            ],
        ];

        $settings = TenantSetting::getGroup('menu');
        
        if (isset($settings['items'])) {
            // Trier par ordre
            usort($settings['items'], function ($a, $b) {
                return ($a['order'] ?? 999) <=> ($b['order'] ?? 999);
            });
            return ['items' => $settings['items']];
        }
        
        return $defaults;
    }

    /**
     * Upload du logo
     */
    public function uploadLogo(UploadedFile $file, string $subdomain): string
    {
        // Utiliser le disque 'public' explicitement
        $disk = Storage::disk('public');
        
        // Créer le répertoire si nécessaire
        $directory = "tenants/{$subdomain}/logos";
        if (!$disk->exists($directory)) {
            $disk->makeDirectory($directory);
        }

        // Supprimer l'ancien logo s'il existe
        $oldLogo = $this->getSetting('logo_url', null);
        if ($oldLogo) {
            try {
                // Extraire le chemin relatif depuis l'URL
                $parsedUrl = parse_url($oldLogo);
                $oldPath = isset($parsedUrl['path']) ? ltrim($parsedUrl['path'], '/') : '';
                
                // Si le chemin commence par storage/, le retirer
                if (strpos($oldPath, 'storage/') === 0) {
                    $oldPath = substr($oldPath, 8); // Retirer "storage/"
                } elseif (strpos($oldPath, '/storage/') === 0) {
                    $oldPath = substr($oldPath, 9); // Retirer "/storage/"
                }
                
                if ($disk->exists($oldPath)) {
                    $disk->delete($oldPath);
                    Log::info("Ancien logo supprimé: {$oldPath}");
                }
            } catch (\Exception $e) {
                Log::warning("Erreur lors de la suppression de l'ancien logo: " . $e->getMessage());
            }
        }

        // Stocker le nouveau logo
        $path = $file->store($directory, 'public');
        
        Log::info("Logo stocké à: {$path}");
        
        // Générer l'URL en utilisant l'URL de la requête actuelle si disponible
        // Sinon utiliser l'URL du disque
        $request = request();
        if ($request && $request->getSchemeAndHttpHost()) {
            // Utiliser l'URL de la requête actuelle pour préserver le sous-domaine
            $url = $request->getSchemeAndHttpHost() . '/storage/' . $path;
        } else {
            // Fallback sur l'URL du disque
            $url = $disk->url($path);
        }

        Log::info("URL du logo générée: {$url}");

        // Sauvegarder l'URL
        $this->setSetting('logo_url', $url, 'branding');
        
        // Vider le cache immédiatement
        try {
            $connection = \Illuminate\Support\Facades\DB::connection();
            $databaseName = $connection->getDatabaseName();
            \Illuminate\Support\Facades\Cache::forget("tenant_settings_group_branding_{$databaseName}");
            \Illuminate\Support\Facades\Cache::forget("tenant_setting_logo_url_{$databaseName}");
            Log::info("Cache vidé pour logo_url dans la base {$databaseName}");
        } catch (\Exception $e) {
            Log::warning("Erreur lors du vidage du cache: " . $e->getMessage());
        }

        return $url;
    }

    /**
     * Générer les variables CSS personnalisées
     */
    public function getCssVariables(): array
    {
        $branding = $this->getBranding();
        
        // Utiliser les valeurs par défaut mises à jour
        $defaultPrimary = '#00286f';
        $defaultSecondary = '#001d4d';
        
        $cssVars = [
            '--primary-color' => $branding['primary_color'] ?? $defaultPrimary,
            '--primary-dark' => $this->darkenColor($branding['primary_color'] ?? $defaultPrimary, 10),
            '--secondary-color' => $branding['secondary_color'] ?? $defaultSecondary,
            '--accent-color' => $branding['accent_color'] ?? '#10b981',
            '--bg-color' => $branding['background_color'] ?? '#f5f7fa',
        ];
        
        \Illuminate\Support\Facades\Log::info("Variables CSS générées", [
            'branding' => $branding,
            'cssVars' => $cssVars
        ]);
        
        return $cssVars;
    }

    /**
     * Assombrir une couleur hexadécimale
     */
    protected function darkenColor(string $hex, int $percent): string
    {
        $hex = str_replace('#', '', $hex);
        $rgb = [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2))
        ];

        foreach ($rgb as &$color) {
            $color = max(0, min(255, $color - ($color * $percent / 100)));
        }

        return '#' . str_pad(dechex($rgb[0]), 2, '0', STR_PAD_LEFT) .
                   str_pad(dechex($rgb[1]), 2, '0', STR_PAD_LEFT) .
                   str_pad(dechex($rgb[2]), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Obtenir les valeurs par défaut pour le branding
     */
    public function getDefaultBranding(): array
    {
        return [
            'primary_color' => '#00286f',
            'secondary_color' => '#001d4d',
            'accent_color' => '#10b981',
            'background_color' => '#f5f7fa',
            'logo_url' => null,
            'organization_name' => null,
            'favicon_url' => null,
        ];
    }

    /**
     * Obtenir les valeurs par défaut pour le layout
     */
    public function getDefaultLayout(): array
    {
        return [
            'welcome_message' => 'Bienvenue sur votre espace',
            'dashboard_widgets' => [
                'stats' => true,
                'activities' => true,
                'calendar' => true,
                'quick_actions' => true,
            ],
            'grid_columns' => 3,
            'spacing' => 'normal',
        ];
    }

    /**
     * Obtenir les valeurs par défaut pour le menu
     */
    public function getDefaultMenu(): array
    {
        return [
            'items' => [
                ['key' => 'dashboard', 'label' => 'Tableau de bord', 'icon' => '📊', 'enabled' => true, 'order' => 1],
                ['key' => 'users', 'label' => 'Utilisateurs', 'icon' => '👥', 'enabled' => true, 'order' => 2],
                ['key' => 'activities', 'label' => 'Activités', 'icon' => '📝', 'enabled' => true, 'order' => 3],
                ['key' => 'reports', 'label' => 'Rapports', 'icon' => '📈', 'enabled' => true, 'order' => 4],
                ['key' => 'settings', 'label' => 'Paramètres', 'icon' => '⚙️', 'enabled' => true, 'order' => 5],
                ['key' => 'customization', 'label' => 'Personnalisation', 'icon' => '🎨', 'enabled' => true, 'order' => 6],
            ],
        ];
    }

    /**
     * Obtenir les variables CSS par défaut
     */
    public function getDefaultCssVariables(): array
    {
        $defaultBranding = $this->getDefaultBranding();
        return [
            '--primary-color' => $defaultBranding['primary_color'],
            '--primary-dark' => $this->darkenColor($defaultBranding['primary_color'], 10),
            '--secondary-color' => $defaultBranding['secondary_color'],
            '--accent-color' => $defaultBranding['accent_color'],
            '--bg-color' => $defaultBranding['background_color'],
        ];
    }

    /**
     * Initialiser les settings par défaut
     */
    public function initializeDefaults(string $organizationName = null): void
    {
        // Branding par défaut
        $this->setSetting('primary_color', '#00286f', 'branding');
        $this->setSetting('secondary_color', '#001d4d', 'branding');
        $this->setSetting('accent_color', '#10b981', 'branding');
        $this->setSetting('background_color', '#f5f7fa', 'branding');
        if ($organizationName) {
            $this->setSetting('organization_name', $organizationName, 'branding');
        }

        // Layout par défaut
        $this->setSetting('welcome_message', 'Bienvenue sur votre espace', 'layout');
        $this->setSetting('dashboard_widgets', [
            'stats' => true,
            'activities' => true,
            'calendar' => true,
            'quick_actions' => true,
        ], 'layout');
        $this->setSetting('grid_columns', 3, 'layout');
        $this->setSetting('spacing', 'normal', 'layout');

        // Menu par défaut
        $this->setSetting('items', [
            ['key' => 'dashboard', 'label' => 'Tableau de bord', 'icon' => '📊', 'enabled' => true, 'order' => 1],
            ['key' => 'users', 'label' => 'Utilisateurs', 'icon' => '👥', 'enabled' => true, 'order' => 2],
            ['key' => 'activities', 'label' => 'Activités', 'icon' => '📝', 'enabled' => true, 'order' => 3],
            ['key' => 'reports', 'label' => 'Rapports', 'icon' => '📈', 'enabled' => true, 'order' => 4],
            ['key' => 'settings', 'label' => 'Paramètres', 'icon' => '⚙️', 'enabled' => true, 'order' => 5],
            ['key' => 'customization', 'label' => 'Personnalisation', 'icon' => '🎨', 'enabled' => true, 'order' => 6],
        ], 'menu');
    }
}
