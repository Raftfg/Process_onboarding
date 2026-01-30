<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\TenantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class TenantController extends Controller
{
    protected $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    /**
     * Affiche le dashboard admin avec les statistiques
     */
    public function dashboard()
    {
        // S'assurer qu'on utilise la base principale
        Config::set('database.default', 'mysql');
        DB::purge('tenant');

        $stats = [
            'total_tenants' => Tenant::count(),
            'active_tenants' => Tenant::where('status', 'active')->count(),
            'suspended_tenants' => Tenant::where('status', 'suspended')->count(),
            'inactive_tenants' => Tenant::where('status', 'inactive')->count(),
            'recent_tenants' => Tenant::orderBy('created_at', 'desc')->limit(5)->get(),
        ];

        return view('admin.dashboard', compact('stats'));
    }

    /**
     * Liste tous les tenants
     */
    public function index(Request $request)
    {
        // S'assurer qu'on utilise la base principale
        Config::set('database.default', 'mysql');
        DB::purge('tenant');

        $query = Tenant::query();

        // Filtres
        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('subdomain', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Tri
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $tenants = $query->paginate(15);

        return view('admin.tenants.index', compact('tenants'));
    }

    /**
     * Affiche les détails d'un tenant
     */
    public function show($id)
    {
        // S'assurer qu'on utilise la base principale
        Config::set('database.default', 'mysql');
        DB::purge('tenant');

        $tenant = Tenant::findOrFail($id);

        // Récupérer les statistiques du tenant
        $tenantStats = $this->getTenantStats($tenant);

        return view('admin.tenants.show', compact('tenant', 'tenantStats'));
    }

    /**
     * Met à jour le statut d'un tenant
     */
    public function updateStatus(Request $request, $id)
    {
        // S'assurer qu'on utilise la base principale
        Config::set('database.default', 'mysql');
        DB::purge('tenant');

        $request->validate([
            'status' => 'required|in:active,suspended,inactive',
        ]);

        $tenant = Tenant::findOrFail($id);
        $oldStatus = $tenant->status;
        $tenant->update(['status' => $request->status]);

        Log::info("Statut du tenant modifié", [
            'tenant_id' => $tenant->id,
            'subdomain' => $tenant->subdomain,
            'old_status' => $oldStatus,
            'new_status' => $request->status,
        ]);

        // Nettoyer le cache
        $this->tenantService->clearTenantCache($tenant->subdomain);

        return redirect()->route('admin.tenants.show', $tenant->id)
            ->with('success', "Statut du tenant mis à jour : {$request->status}");
    }

    /**
     * Supprime un tenant (soft delete)
     */
    public function destroy($id)
    {
        // S'assurer qu'on utilise la base principale
        Config::set('database.default', 'mysql');
        DB::purge('tenant');

        $tenant = Tenant::findOrFail($id);
        $subdomain = $tenant->subdomain;

        $tenant->delete();

        Log::warning("Tenant supprimé", [
            'tenant_id' => $tenant->id,
            'subdomain' => $subdomain,
        ]);

        // Nettoyer le cache
        $this->tenantService->clearTenantCache($subdomain);

        return redirect()->route('admin.tenants.index')
            ->with('success', 'Tenant supprimé avec succès');
    }

    /**
     * Restaure un tenant supprimé
     */
    public function restore($id)
    {
        // S'assurer qu'on utilise la base principale
        Config::set('database.default', 'mysql');
        DB::purge('tenant');

        $tenant = Tenant::onlyTrashed()->findOrFail($id);
        $tenant->restore();

        Log::info("Tenant restauré", [
            'tenant_id' => $tenant->id,
            'subdomain' => $tenant->subdomain,
        ]);

        // Nettoyer le cache
        $this->tenantService->clearTenantCache($tenant->subdomain);

        return redirect()->route('admin.tenants.show', $tenant->id)
            ->with('success', 'Tenant restauré avec succès');
    }

    /**
     * Récupère les statistiques d'un tenant
     */
    protected function getTenantStats(Tenant $tenant): array
    {
        try {
            // Basculer vers la base du tenant
            $this->tenantService->switchToTenantDatabase($tenant->database_name);

            $stats = [
                'total_users' => DB::table('users')->count(),
                'admin_users' => DB::table('users')->where('role', 'admin')->count(),
                'regular_users' => DB::table('users')->where('role', 'user')->count(),
                'manager_users' => DB::table('users')->where('role', 'manager')->count(),
                'total_personnes' => DB::table('information_personnes')->count(),
                'dashboard_configs' => DB::table('configuration_dashboard')->count(),
            ];

            // Revenir à la base principale
            Config::set('database.default', 'mysql');
            DB::purge('tenant');

            return $stats;
        } catch (\Exception $e) {
            Log::error("Erreur lors de la récupération des stats du tenant: " . $e->getMessage());
            
            // Revenir à la base principale en cas d'erreur
            Config::set('database.default', 'mysql');
            DB::purge('tenant');

            return [
                'total_users' => 0,
                'admin_users' => 0,
                'regular_users' => 0,
                'manager_users' => 0,
                'total_personnes' => 0,
                'dashboard_configs' => 0,
                'error' => 'Impossible de récupérer les statistiques',
            ];
        }
    }
}
