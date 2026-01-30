<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardService
{
    /**
     * Récupère les statistiques générales
     */
    public function getStats(): array
    {
        return Cache::remember('dashboard_stats', 300, function () {
            $totalUsers = User::count();
            $activeUsers = User::where('status', 'active')->count();
            $todayActivities = Activity::whereDate('created_at', today())->count();
            $unreadNotifications = Notification::unread()->count();
            $recentActivities = Activity::recent(5)->count();
            
            // Statistiques par type d'activité (7 derniers jours)
            $activitiesByType = Activity::where('created_at', '>=', now()->subDays(7))
                ->select('type', DB::raw('count(*) as count'))
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray();
            
            return [
                'total_users' => $totalUsers,
                'active_users' => $activeUsers,
                'today_activities' => $todayActivities,
                'unread_notifications' => $unreadNotifications,
                'recent_activities' => $recentActivities,
                'activities_by_type' => $activitiesByType,
            ];
        });
    }

    /**
     * Récupère les activités récentes
     */
    public function getRecentActivities(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return Activity::with('user')
            ->recent($limit)
            ->get();
    }

    /**
     * Récupère les notifications non lues pour un utilisateur
     */
    public function getUnreadNotifications(int $userId, int $limit = 10): Collection
    {
        return Notification::where('user_id', $userId)
            ->unread()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Crée une nouvelle activité
     */
    public function createActivity(int $userId, string $type, string $description, array $metadata = []): Activity
    {
        try {
            $activity = Activity::create([
                'user_id' => $userId,
                'type' => $type,
                'description' => $description,
                'metadata' => $metadata,
            ]);

            // Invalider le cache des statistiques
            Cache::forget('dashboard_stats');

            Log::info("Activité créée", [
                'user_id' => $userId,
                'type' => $type,
                'activity_id' => $activity->id,
            ]);

            return $activity;
        } catch (\Exception $e) {
            Log::error("Erreur lors de la création d'une activité: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Crée une nouvelle notification
     */
    public function createNotification(int $userId, string $type, string $title, string $message, array $data = []): Notification
    {
        try {
            $notification = Notification::create([
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'data' => $data,
            ]);

            // Invalider le cache des statistiques
            Cache::forget('dashboard_stats');

            Log::info("Notification créée", [
                'user_id' => $userId,
                'type' => $type,
                'notification_id' => $notification->id,
            ]);

            return $notification;
        } catch (\Exception $e) {
            Log::error("Erreur lors de la création d'une notification: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Récupère les données pour les graphiques
     */
    public function getChartData(string $period = 'week'): array
    {
        $days = $period === 'week' ? 7 : ($period === 'month' ? 30 : 7);
        
        $activities = Activity::where('created_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        $labels = [];
        $data = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $labels[] = now()->subDays($i)->format('d/m');
            $activity = $activities->firstWhere('date', $date);
            $data[] = $activity ? $activity->count : 0;
        }

        return [
            'labels' => $labels,
            'data' => $data,
        ];
    }

    /**
     * Recherche globale
     */
    public function search(string $query, int $limit = 10): array
    {
        $results = [
            'users' => [],
            'activities' => [],
        ];

        if (strlen($query) < 2) {
            return $results;
        }

        // Recherche dans les utilisateurs
        $results['users'] = User::where('name', 'like', "%{$query}%")
            ->orWhere('email', 'like', "%{$query}%")
            ->limit($limit)
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'type' => 'user',
                ];
            })
            ->toArray();

        // Recherche dans les activités
        $results['activities'] = Activity::where('description', 'like', "%{$query}%")
            ->with('user')
            ->limit($limit)
            ->get()
            ->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'description' => $activity->description,
                    'type' => $activity->type,
                    'user' => $activity->user->name ?? 'N/A',
                    'date' => $activity->created_at->format('d/m/Y H:i'),
                    'type' => 'activity',
                ];
            })
            ->toArray();

        return $results;
    }
}
