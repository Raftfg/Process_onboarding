<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OnboardingRegistration;
use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class OnboardingMonitoringController extends Controller
{
    /**
     * Affiche la vue globale de tous les onboardings
     */
    public function index(Request $request)
    {
        $query = OnboardingRegistration::with('application');

        // Filtres
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('application_id')) {
            $query->where('application_id', $request->application_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                  ->orWhere('organization_name', 'like', "%{$search}%")
                  ->orWhere('subdomain', 'like', "%{$search}%")
                  ->orWhere('uuid', 'like', "%{$search}%");
            });
        }

        $onboardings = $query->orderBy('created_at', 'desc')->paginate(50);

        // Statistiques
        $stats = $this->getStats();

        // Applications pour le filtre
        $applications = Application::orderBy('app_name')->get();

        // Onboardings bloqués (pending > 24h)
        $stuckOnboardings = OnboardingRegistration::where('status', 'pending')
            ->where('created_at', '<', now()->subHours(24))
            ->count();

        return view('admin.monitoring.onboardings', [
            'onboardings' => $onboardings,
            'stats' => $stats,
            'applications' => $applications,
            'stuckOnboardings' => $stuckOnboardings,
            'filters' => $request->only(['status', 'application_id', 'date_from', 'date_to', 'search']),
        ]);
    }

    /**
     * Exporte les onboardings en CSV
     */
    public function export(Request $request)
    {
        $query = OnboardingRegistration::with('application');

        // Appliquer les mêmes filtres que index
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('application_id')) {
            $query->where('application_id', $request->application_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $onboardings = $query->orderBy('created_at', 'desc')->get();

        $filename = 'onboardings_' . now()->format('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($onboardings) {
            $file = fopen('php://output', 'w');
            
            // En-têtes
            fputcsv($file, [
                'UUID',
                'Application',
                'Email',
                'Organisation',
                'Sous-domaine',
                'Statut',
                'DNS Configuré',
                'SSL Configuré',
                'Tentatives Provisioning',
                'Créé le',
                'Mis à jour le',
            ]);

            // Données
            foreach ($onboardings as $onboarding) {
                fputcsv($file, [
                    $onboarding->uuid,
                    $onboarding->application->app_name ?? 'N/A',
                    $onboarding->email,
                    $onboarding->organization_name,
                    $onboarding->subdomain,
                    $onboarding->status,
                    $onboarding->dns_configured ? 'Oui' : 'Non',
                    $onboarding->ssl_configured ? 'Oui' : 'Non',
                    $onboarding->provisioning_attempts ?? 0,
                    $onboarding->created_at->format('Y-m-d H:i:s'),
                    $onboarding->updated_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Récupère les statistiques d'onboarding
     */
    private function getStats(): array
    {
        $total = OnboardingRegistration::count();
        $pending = OnboardingRegistration::where('status', 'pending')->count();
        $activated = OnboardingRegistration::where('status', 'activated')->count();
        $cancelled = OnboardingRegistration::where('status', 'cancelled')->count();
        $completed = OnboardingRegistration::where('status', 'completed')->count();

        // Taux de succès
        $successRate = $total > 0 ? round(($activated / $total) * 100, 2) : 0;

        // Temps moyen de provisioning (pour les activés)
        $avgProvisioningTime = OnboardingRegistration::where('status', 'activated')
            ->whereNotNull('updated_at')
            ->get()
            ->map(function($onboarding) {
                return $onboarding->created_at->diffInMinutes($onboarding->updated_at);
            })
            ->avg();

        // Onboardings par application
        $byApplication = OnboardingRegistration::select('application_id', DB::raw('count(*) as count'))
            ->with('application')
            ->groupBy('application_id')
            ->get()
            ->mapWithKeys(function($item) {
                return [$item->application->app_name ?? 'Unknown' => $item->count];
            })
            ->toArray();

        return [
            'total' => $total,
            'pending' => $pending,
            'activated' => $activated,
            'cancelled' => $cancelled,
            'completed' => $completed,
            'success_rate' => $successRate,
            'avg_provisioning_time_minutes' => round($avgProvisioningTime ?? 0, 2),
            'by_application' => $byApplication,
        ];
    }
}
