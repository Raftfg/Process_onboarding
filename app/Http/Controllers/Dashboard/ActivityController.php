<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActivityController extends Controller
{
    protected $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Liste des activités avec pagination
     */
    public function index()
    {
        $activities = Activity::with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('dashboard.activities', compact('activities'));
    }

    /**
     * Crée une nouvelle activité
     */
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|string',
            'description' => 'required|string',
            'metadata' => 'nullable|array',
        ]);

        $activity = $this->dashboardService->createActivity(
            Auth::id(),
            $request->type,
            $request->description,
            $request->metadata ?? []
        );

        return response()->json([
            'success' => true,
            'activity' => $activity,
        ], 201);
    }
}
