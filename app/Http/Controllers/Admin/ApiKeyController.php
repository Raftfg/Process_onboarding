<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ApiKeyController extends Controller
{
    /**
     * Liste toutes les clés API
     */
    public function index()
    {
        $keys = ApiKey::orderBy('created_at', 'desc')->get();
        return view('admin.api-keys.index', compact('keys'));
    }

    /**
     * Crée une nouvelle clé API
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'app_name' => 'required|string|max:50|alpha_dash', // Nom technique unique par app
            'expires_at' => 'nullable|date|after:now',
            'rate_limit' => 'nullable|integer|min:1',
        ]);

        try {
            $result = ApiKey::generate($request->name, [
                'app_name' => $request->app_name,
                'expires_at' => $request->expires_at,
                'rate_limit' => $request->rate_limit ?? 100,
            ]);

            return redirect()->route('admin.api-keys.index')
                ->with('success', 'Clé API générée avec succès !')
                ->with('new_api_key', $result['key'])
                ->with('new_api_key_name', $result['name'])
                ->with('new_api_key_app', $result['app_name']);
        } catch (\Exception $e) {
            return back()->with('error', 'Erreur lors de la génération : ' . $e->getMessage());
        }
    }

    /**
     * Désactiver/Activer une clé
     */
    public function toggleStatus($id)
    {
        $key = ApiKey::findOrFail($id);
        $key->update(['is_active' => !$key->is_active]);

        $status = $key->is_active ? 'activée' : 'désactivée';
        return back()->with('success', "La clé API a été {$status} avec succès.");
    }

    /**
     * Supprimer une clé
     */
    public function destroy($id)
    {
        $key = ApiKey::findOrFail($id);
        $key->delete();

        return back()->with('success', 'Clé API supprimée avec succès.');
    }

    /**
     * Affiche le formulaire de configuration de l'API
     */
    public function editConfig($id)
    {
        $key = ApiKey::findOrFail($id);
        $config = $key->api_config ?? ApiKey::getDefaultApiConfig();
        
        return view('admin.api-keys.config', compact('key', 'config'));
    }

    /**
     * Met à jour la configuration de l'API
     */
    public function updateConfig(Request $request, $id)
    {
        $key = ApiKey::findOrFail($id);

        $request->validate([
            'require_organization_name' => 'nullable|boolean',
            'organization_name_generation_strategy' => 'nullable|in:auto,email,timestamp,metadata,custom',
            'organization_name_template' => 'nullable|string|max:255',
        ]);

        try {
            $currentConfig = $key->api_config ?? ApiKey::getDefaultApiConfig();
            
            $newConfig = [
                'require_organization_name' => $request->has('require_organization_name') ? (bool) $request->require_organization_name : $currentConfig['require_organization_name'],
                'organization_name_generation_strategy' => $request->organization_name_generation_strategy ?? $currentConfig['organization_name_generation_strategy'],
                'organization_name_template' => $request->organization_name_template ?? $currentConfig['organization_name_template'],
                'custom_validation_rules' => $currentConfig['custom_validation_rules'] ?? [],
            ];

            // Si la stratégie n'est pas "custom", supprimer le template
            if ($newConfig['organization_name_generation_strategy'] !== 'custom') {
                $newConfig['organization_name_template'] = null;
            }

            $key->update(['api_config' => $newConfig]);

            return redirect()->route('admin.api-keys.index')
                ->with('success', 'Configuration de l\'API mise à jour avec succès.');
        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise à jour de la config API: ' . $e->getMessage());
            return back()->with('error', 'Erreur lors de la mise à jour : ' . $e->getMessage());
        }
    }
}
