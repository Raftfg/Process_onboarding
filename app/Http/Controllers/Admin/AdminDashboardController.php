<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminService;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    protected $adminService;

    public function __construct(AdminService $adminService)
    {
        $this->adminService = $adminService;
    }

    /**
     * Affiche le dashboard central avec statistiques globales
     */
    public function index()
    {
        $stats = $this->adminService->getGlobalStats();
        
        // Récupérer les derniers tenants créés
        $recentTenants = \App\Models\OnboardingSession::on('mysql')
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('admin.dashboard.index', [
            'stats' => $stats,
            'recentTenants' => $recentTenants,
        ]);
    }
}
