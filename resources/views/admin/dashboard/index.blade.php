@extends('admin.layouts.app')

@section('title', 'Dashboard Administrateur')

@section('content')
    <style>
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .dashboard-header h1 {
            font-size: 28px;
            font-weight: 700;
            color: #1a202c;
        }

        .quick-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            transition: transform 0.2s, box-shadow 0.2s;
            border-left: 4px solid var(--primary-color);
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card h3 {
            font-size: 13px;
            color: #718096;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .stat-card .value {
            font-size: 36px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 8px;
        }

        .stat-card .sub-value {
            font-size: 14px;
            color: #718096;
            margin-top: 8px;
        }

        .stat-card.stat-success {
            border-left-color: #10b981;
        }

        .stat-card.stat-success .value {
            color: #10b981;
        }

        .stat-card.stat-warning {
            border-left-color: #f59e0b;
        }

        .stat-card.stat-warning .value {
            color: #f59e0b;
        }

        .stat-card.stat-danger {
            border-left-color: #ef4444;
        }

        .stat-card.stat-danger .value {
            color: #ef4444;
        }

        .stat-card.stat-info {
            border-left-color: #3b82f6;
        }

        .stat-card.stat-info .value {
            color: #3b82f6;
        }

        .alert-card {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
        }

        .alert-card.alert-danger {
            background: #fee2e2;
            border-left-color: #ef4444;
        }

        .alert-card h3 {
            color: #856404;
            margin-bottom: 5px;
            font-size: 16px;
            font-weight: 600;
        }

        .alert-card.alert-danger h3 {
            color: #991b1b;
        }

        .alert-card p {
            color: #856404;
            margin: 0;
            font-size: 14px;
        }

        .alert-card.alert-danger p {
            color: #991b1b;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 25px;
            margin-bottom: 25px;
        }

        .card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 25px;
            box-shadow: var(--shadow);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border-color);
        }

        .card-header h2 {
            font-size: 20px;
            font-weight: 600;
            color: #1a202c;
        }

        .card-header .btn {
            padding: 8px 16px;
            font-size: 14px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th,
        table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
        }

        table th {
            font-weight: 600;
            background: var(--bg-color);
            color: #4a5568;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }

        table td {
            color: #2d3748;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #718096;
        }

        .empty-state p {
            margin-top: 10px;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 8px;
        }

        .progress-fill {
            height: 100%;
            background: var(--primary-color);
            transition: width 0.3s;
        }

        .progress-fill.success {
            background: #10b981;
        }

        .progress-fill.warning {
            background: #f59e0b;
        }

        .progress-fill.danger {
            background: #ef4444;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
            }

            table {
                font-size: 12px;
            }

            table th,
            table td {
                padding: 8px;
            }

            .card {
                padding: 15px;
            }
        }

        @media (max-width: 480px) {
            .stat-card .value {
                font-size: 28px;
            }

            .card-header h2 {
                font-size: 18px;
            }
        }
    </style>

    <div class="dashboard-header">
        <h1>Dashboard Administrateur</h1>
        <div class="quick-actions">
            <a href="{{ route('admin.monitoring.onboardings') }}" class="btn btn-primary">
                Monitoring Onboardings
            </a>
        </div>
    </div>

    <!-- Alertes -->
    @if(isset($stats['onboarding_stats']) && ($stats['onboarding_stats']['stuck'] ?? 0) > 0)
        <div class="alert-card alert-danger">
            <div>
                <h3>⚠️ Onboardings bloqués</h3>
                <p>
                    <strong>{{ $stats['onboarding_stats']['stuck'] }}</strong> onboarding(s) en attente depuis plus de 24h nécessitent une attention.
                </p>
            </div>
            <a href="{{ route('admin.monitoring.onboardings', ['status' => 'pending']) }}" class="btn btn-primary">
                Voir les détails
            </a>
        </div>
    @endif

    <!-- Statistiques Globales -->
    <div class="stats-grid">
        <div class="stat-card stat-info">
            <h3>Total Tenants</h3>
            <div class="value">{{ number_format($stats['total_tenants']) }}</div>
            <div class="sub-value">Tenants créés</div>
        </div>
        
        <div class="stat-card stat-success">
            <h3>Tenants Actifs</h3>
            <div class="value">{{ number_format($stats['active_tenants']) }}</div>
            <div class="sub-value">Avec base de données</div>
        </div>
        
        <div class="stat-card">
            <h3>Utilisateurs Totaux</h3>
            <div class="value">{{ number_format($stats['total_users']) }}</div>
            <div class="sub-value">Tous tenants confondus</div>
        </div>
        
        <div class="stat-card">
            <h3>Activités Totales</h3>
            <div class="value">{{ number_format($stats['total_activities']) }}</div>
            <div class="sub-value">Activités enregistrées</div>
        </div>
    </div>

    <!-- Statistiques Onboarding Stateless -->
    @if(isset($stats['onboarding_stats']))
        <div style="margin-bottom: 30px;">
            <h2 style="font-size: 22px; font-weight: 600; margin-bottom: 20px; color: #1a202c;">
                📊 Statistiques Onboarding Stateless
            </h2>
            
            <div class="stats-grid">
                <div class="stat-card stat-info">
                    <h3>Total Onboardings</h3>
                    <div class="value">{{ number_format($stats['onboarding_stats']['total'] ?? 0) }}</div>
                    <div class="sub-value">
                        {{ $stats['onboarding_stats']['last_7_days'] ?? 0 }} cette semaine
                    </div>
                </div>

                <div class="stat-card stat-success">
                    <h3>Onboardings Activés</h3>
                    <div class="value">{{ number_format($stats['onboarding_stats']['activated'] ?? 0) }}</div>
                    <div class="sub-value">
                        Taux de succès: {{ $stats['onboarding_stats']['success_rate'] ?? 0 }}%
                    </div>
                    @if(isset($stats['onboarding_stats']['total']) && $stats['onboarding_stats']['total'] > 0)
                        <div class="progress-bar">
                            <div class="progress-fill success" style="width: {{ $stats['onboarding_stats']['success_rate'] ?? 0 }}%"></div>
                        </div>
                    @endif
                </div>

                <div class="stat-card stat-warning">
                    <h3>En Attente</h3>
                    <div class="value">{{ number_format($stats['onboarding_stats']['pending'] ?? 0) }}</div>
                    <div class="sub-value">
                        @if(($stats['onboarding_stats']['stuck'] ?? 0) > 0)
                            <span style="color: #ef4444;">{{ $stats['onboarding_stats']['stuck'] }} bloqués</span>
                        @else
                            En cours de traitement
                        @endif
                    </div>
                </div>

                <div class="stat-card stat-danger">
                    <h3>Annulés</h3>
                    <div class="value">{{ number_format($stats['onboarding_stats']['cancelled'] ?? 0) }}</div>
                    <div class="sub-value">Onboardings annulés</div>
                </div>

                <div class="stat-card">
                    <h3>Temps Moyen Provisioning</h3>
                    <div class="value">{{ $stats['onboarding_stats']['avg_provisioning_time_minutes'] ?? 0 }}</div>
                    <div class="sub-value">Minutes</div>
                </div>

                <div class="stat-card">
                    <h3>30 Derniers Jours</h3>
                    <div class="value">{{ number_format($stats['onboarding_stats']['last_30_days'] ?? 0) }}</div>
                    <div class="sub-value">Onboardings créés</div>
                </div>
            </div>

            @if(!empty($stats['onboarding_stats']['by_application']))
                <div class="card" style="margin-top: 25px;">
                    <h2 style="font-size: 18px; font-weight: 600; margin-bottom: 15px;">Onboardings par Application</h2>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        @foreach($stats['onboarding_stats']['by_application'] as $appName => $count)
                            <div style="padding: 15px; background: var(--bg-color); border-radius: 8px;">
                                <div style="font-weight: 600; color: #4a5568; margin-bottom: 5px;">{{ $appName }}</div>
                                <div style="font-size: 24px; font-weight: 700; color: var(--primary-color);">{{ $count }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif

    <!-- Derniers Onboardings Stateless -->
    <div class="dashboard-grid">
        <div class="card">
            <div class="card-header">
                <h2>Derniers Onboardings Stateless</h2>
                <a href="{{ route('admin.monitoring.onboardings') }}" class="btn btn-primary">
                    Voir tout
                </a>
            </div>
            
            @if($recentOnboardings->count() > 0)
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Email</th>
                                <th>Organisation</th>
                                <th>Sous-domaine</th>
                                <th>Statut</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentOnboardings as $onboarding)
                                <tr>
                                    <td>{{ \Illuminate\Support\Str::limit($onboarding->email, 25) }}</td>
                                    <td>{{ \Illuminate\Support\Str::limit($onboarding->organization_name ?? 'N/A', 20) }}</td>
                                    <td><code style="font-size: 11px;">{{ $onboarding->subdomain }}</code></td>
                                    <td>
                                        @if($onboarding->status === 'activated')
                                            <span class="badge badge-success">Activé</span>
                                        @elseif($onboarding->status === 'pending')
                                            <span class="badge badge-warning">En attente</span>
                                        @else
                                            <span class="badge badge-danger">Annulé</span>
                                        @endif
                                    </td>
                                    <td>{{ $onboarding->created_at->format('d/m/Y H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-state">
                    <p>Aucun onboarding stateless pour le moment.</p>
                </div>
            @endif
        </div>

        <!-- Applications Actives -->
        <div class="card">
            <div class="card-header">
                <h2>Applications Actives</h2>
            </div>
            
            @if($applications->count() > 0)
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Email</th>
                                <th>Créée le</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($applications as $app)
                                <tr>
                                    <td><strong>{{ $app->display_name }}</strong></td>
                                    <td>{{ $app->contact_email }}</td>
                                    <td>{{ $app->created_at->format('d/m/Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-state">
                    <p>Aucune application active.</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Derniers Tenants Créés -->
    <div class="card">
        <div class="card-header">
            <h2>Derniers Tenants Créés</h2>
        </div>
        
        @if($recentTenants->count() > 0)
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Organisation</th>
                            <th>Sous-domaine</th>
                            <th>Email Admin</th>
                            <th>Base de données</th>
                            <th>Créé le</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentTenants as $tenant)
                            <tr>
                                <td>
                                    <strong>{{ $tenant->organization_name }}</strong>
                                </td>
                                <td>
                                    <code>{{ $tenant->subdomain }}</code>
                                </td>
                                <td>{{ $tenant->admin_email }}</td>
                                <td>
                                    <code style="font-size: 12px;">{{ $tenant->database_name ?? 'N/A' }}</code>
                                </td>
                                <td>{{ $tenant->created_at->format('d/m/Y H:i') }}</td>
                                <td>
                                    <a href="{{ route('admin.tenants.show', $tenant->id) }}" class="btn btn-primary" style="padding: 6px 12px; font-size: 12px;">Voir</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="empty-state">
                <p>Aucun tenant créé pour le moment.</p>
            </div>
        @endif
    </div>
@endsection
