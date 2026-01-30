<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OnboardingSession;
use App\Services\AdminService;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    protected $adminService;

    public function __construct(AdminService $adminService)
    {
        $this->adminService = $adminService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = OnboardingSession::on('mysql')->where('status', 'completed');

        // Filtres
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('hospital_name', 'like', "%{$search}%")
                  ->orWhere('subdomain', 'like', "%{$search}%")
                  ->orWhere('admin_email', 'like', "%{$search}%");
            });
        }

        $tenants = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('admin.tenants.index', [
            'tenants' => $tenants,
            'search' => $request->search,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Les tenants sont créés via le processus d'onboarding
        return redirect()->route('admin.tenants.index');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Les tenants sont créés via le processus d'onboarding
        return redirect()->route('admin.tenants.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $tenant = OnboardingSession::on('mysql')->findOrFail($id);
        $stats = $this->adminService->getTenantStats($id);
        $users = $this->adminService->getTenantUsers($id, 20);

        return view('admin.tenants.show', [
            'tenant' => $tenant,
            'stats' => $stats,
            'users' => $users,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        // Pour l'instant, pas d'édition
        return redirect()->route('admin.tenants.show', $id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        // Pour l'instant, pas de mise à jour
        return redirect()->route('admin.tenants.show', $id);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $this->adminService->deleteTenant($id);
            return redirect()->route('admin.tenants.index')
                ->with('success', 'Tenant supprimé avec succès.');
        } catch (\Exception $e) {
            return redirect()->route('admin.tenants.show', $id)
                ->with('error', 'Erreur lors de la suppression: ' . $e->getMessage());
        }
    }

    /**
     * Obtenir les statistiques d'un tenant (AJAX)
     */
    public function getStats(string $id)
    {
        $stats = $this->adminService->getTenantStats($id);
        return response()->json($stats);
    }

    /**
     * Activer/désactiver un tenant
     */
    public function toggleStatus(string $id)
    {
        try {
            $this->adminService->toggleTenantStatus($id);
            return redirect()->route('admin.tenants.show', $id)
                ->with('success', 'Statut du tenant mis à jour.');
        } catch (\Exception $e) {
            return redirect()->route('admin.tenants.show', $id)
                ->with('error', 'Erreur lors de la mise à jour: ' . $e->getMessage());
        }
    }
}
