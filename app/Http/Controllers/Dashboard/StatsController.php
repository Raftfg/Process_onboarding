<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    protected $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Récupère les statistiques en temps réel
     */
    public function index()
    {
        return response()->json($this->dashboardService->getStats());
    }

    /**
     * Récupère les données pour les graphiques
     */
    public function chart(Request $request)
    {
        $period = $request->get('period', 'week');
        return response()->json($this->dashboardService->getChartData($period));
    }
}
