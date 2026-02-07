@extends('admin.layouts.app')

@section('title', 'Monitoring des Onboardings')

@section('content')
    <style>
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }
        .badge-activated {
            background: #d1fae5;
            color: #065f46;
        }
        .badge-completed {
            background: #dbeafe;
            color: #1e40af;
        }
        .badge-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }
    </style>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <h1 style="font-size: 24px; font-weight: 600;">Monitoring des Onboardings</h1>
        <a href="{{ route('admin.monitoring.onboardings.export', request()->query()) }}" class="btn btn-primary" style="padding: 10px 20px;">Exporter en CSV</a>
    </div>

    @if($stuckOnboardings > 0)
        <div class="card" style="background: #fff3cd; border-left: 4px solid #ffc107; margin-bottom: 20px;">
            <h2 style="color: #856404; margin-bottom: 10px;">⚠️ Alertes</h2>
            <p style="color: #856404;">
                <strong>{{ $stuckOnboardings }}</strong> onboarding(s) en attente depuis plus de 24h.
            </p>
        </div>
    @endif

    <div class="stats-grid" style="margin-bottom: 30px;">
        <div class="stat-card">
            <h3>Total</h3>
            <div class="value">{{ $stats['total'] }}</div>
        </div>
        <div class="stat-card">
            <h3>En attente</h3>
            <div class="value">{{ $stats['pending'] }}</div>
        </div>
        <div class="stat-card">
            <h3>Activés</h3>
            <div class="value">{{ $stats['activated'] }}</div>
        </div>
        <div class="stat-card">
            <h3>Annulés</h3>
            <div class="value">{{ $stats['cancelled'] }}</div>
        </div>
        <div class="stat-card">
            <h3>Complétés</h3>
            <div class="value">{{ $stats['completed'] ?? 0 }}</div>
        </div>
        <div class="stat-card">
            <h3>Taux de succès</h3>
            <div class="value">{{ $stats['success_rate'] }}%</div>
        </div>
        <div class="stat-card">
            <h3>Temps moyen provisioning</h3>
            <div class="value">{{ $stats['avg_provisioning_time_minutes'] }} min</div>
        </div>
    </div>

    <div class="card">
        <h2 style="margin-bottom: 20px;">Filtres</h2>
        <form method="GET" action="{{ route('admin.monitoring.onboardings') }}" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
            <div>
                <label style="display: block; margin-bottom: 5px; font-size: 14px;">Statut</label>
                <select name="status" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="">Tous</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>En attente</option>
                    <option value="activated" {{ request('status') === 'activated' ? 'selected' : '' }}>Activé</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Complété</option>
                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Annulé</option>
                </select>
            </div>
            <div>
                <label style="display: block; margin-bottom: 5px; font-size: 14px;">Application</label>
                <select name="application_id" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="">Toutes</option>
                    @foreach($applications as $app)
                        <option value="{{ $app->id }}" {{ request('application_id') == $app->id ? 'selected' : '' }}>
                            {{ $app->app_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="display: block; margin-bottom: 5px; font-size: 14px;">Date de début</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 5px; font-size: 14px;">Date de fin</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 5px; font-size: 14px;">Recherche</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Email, organisation, UUID..." style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div style="display: flex; align-items: flex-end;">
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 10px;">Filtrer</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h2 style="margin-bottom: 20px;">Liste des Onboardings</h2>
        @if($onboardings->count() > 0)
            <table>
                <thead>
                    <tr>
                        <th>UUID</th>
                        <th>Application</th>
                        <th>Email</th>
                        <th>Organisation</th>
                        <th>Sous-domaine</th>
                        <th>Statut</th>
                        <th>DNS</th>
                        <th>SSL</th>
                        <th>Tentatives</th>
                        <th>Créé le</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($onboardings as $onboarding)
                        <tr>
                            <td><code style="font-size: 11px;">{{ substr($onboarding->uuid, 0, 8) }}...</code></td>
                            <td>{{ $onboarding->application->app_name ?? 'N/A' }}</td>
                            <td>{{ $onboarding->email }}</td>
                            <td>{{ $onboarding->organization_name }}</td>
                            <td><code>{{ $onboarding->subdomain }}</code></td>
                            <td>
                                <span class="badge badge-{{ $onboarding->status }}">
                                    {{ $onboarding->status }}
                                </span>
                            </td>
                            <td>{{ $onboarding->dns_configured ? '✓' : '✗' }}</td>
                            <td>{{ $onboarding->ssl_configured ? '✓' : '✗' }}</td>
                            <td>{{ $onboarding->provisioning_attempts ?? 0 }}</td>
                            <td>{{ $onboarding->created_at->format('d/m/Y H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div style="margin-top: 20px;">
                {{ $onboardings->links() }}
            </div>
        @else
            <p style="color: #666; text-align: center; padding: 40px;">Aucun onboarding trouvé.</p>
        @endif
    </div>
@endsection
