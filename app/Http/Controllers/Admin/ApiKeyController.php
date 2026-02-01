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
            'expires_at' => 'nullable|date|after:now',
            'rate_limit' => 'nullable|integer|min:1',
        ]);

        try {
            $result = ApiKey::generate($request->name, [
                'expires_at' => $request->expires_at,
                'rate_limit' => $request->rate_limit ?? 100,
            ]);

            return redirect()->route('admin.api-keys.index')
                ->with('success', 'Clé API générée avec succès !')
                ->with('new_api_key', $result['key'])
                ->with('new_api_key_name', $result['name']);
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
}
