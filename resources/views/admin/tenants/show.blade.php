<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails Tenant - MedKey Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f5f7fa;
            color: #333;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 24px;
            font-weight: 600;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
            display: inline-block;
        }

        .btn-primary {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .btn-primary:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .container {
            max-width: 1400px;
            margin: 40px auto;
            padding: 0 30px;
        }

        .section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .section h2 {
            font-size: 20px;
            margin-bottom: 20px;
            color: #333;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .info-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.active {
            background: #d1fae5;
            color: #065f46;
        }

        .status-badge.suspended {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-badge.inactive {
            background: #e5e7eb;
            color: #374151;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .stat-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 600;
            color: #667eea;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 10px 20px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-action.active {
            background: #10b981;
            color: white;
        }

        .btn-action.suspend {
            background: #f59e0b;
            color: white;
        }

        .btn-action.inactive {
            background: #6b7280;
            color: white;
        }

        .btn-action.danger {
            background: #ef4444;
            color: white;
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .detail-row {
            display: flex;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 600;
            color: #666;
            width: 200px;
            flex-shrink: 0;
        }

        .detail-value {
            color: #333;
            flex: 1;
        }

        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .actions {
                flex-direction: column;
            }

            .btn-action {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>📋 Détails du Tenant</h1>
            <div class="header-actions">
                <a href="{{ route('admin.tenants.index') }}" class="btn btn-primary">← Retour à la liste</a>
                <a href="{{ route('admin.dashboard') }}" class="btn btn-primary">Dashboard</a>
                <form method="POST" action="{{ route('admin.logout') }}" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn btn-primary">Déconnexion</button>
                </form>
            </div>
        </div>
    </div>

    <div class="container">
        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if(isset($tenantStats['error']))
            <div class="alert alert-error">
                {{ $tenantStats['error'] }}
            </div>
        @endif

        <div class="section">
            <h2>Informations générales</h2>
            
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Statut</div>
                    <div class="info-value">
                        <span class="status-badge {{ $tenant->status }}">
                            {{ ucfirst($tenant->status) }}
                        </span>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-label">Sous-domaine</div>
                    <div class="info-value"><code>{{ $tenant->subdomain }}</code></div>
                </div>

                <div class="info-item">
                    <div class="info-label">Base de données</div>
                    <div class="info-value"><code>{{ $tenant->database_name }}</code></div>
                </div>

                <div class="info-item">
                    <div class="info-label">Plan</div>
                    <div class="info-value">{{ $tenant->plan ?? 'N/A' }}</div>
                </div>
            </div>

            <div class="detail-row">
                <div class="detail-label">Nom</div>
                <div class="detail-value">{{ $tenant->name }}</div>
            </div>

            @if($tenant->email)
            <div class="detail-row">
                <div class="detail-label">Email</div>
                <div class="detail-value">{{ $tenant->email }}</div>
            </div>
            @endif

            @if($tenant->phone)
            <div class="detail-row">
                <div class="detail-label">Téléphone</div>
                <div class="detail-value">{{ $tenant->phone }}</div>
            </div>
            @endif

            @if($tenant->address)
            <div class="detail-row">
                <div class="detail-label">Adresse</div>
                <div class="detail-value">{{ $tenant->address }}</div>
            </div>
            @endif

            <div class="detail-row">
                <div class="detail-label">Créé le</div>
                <div class="detail-value">{{ $tenant->created_at->format('d/m/Y à H:i') }}</div>
            </div>

            <div class="detail-row">
                <div class="detail-label">Modifié le</div>
                <div class="detail-value">{{ $tenant->updated_at->format('d/m/Y à H:i') }}</div>
            </div>
        </div>

        <div class="section">
            <h2>Statistiques du Tenant</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value">{{ $tenantStats['total_users'] ?? 0 }}</div>
                    <div class="stat-label">Utilisateurs totaux</div>
                </div>

                <div class="stat-card">
                    <div class="stat-value">{{ $tenantStats['admin_users'] ?? 0 }}</div>
                    <div class="stat-label">Administrateurs</div>
                </div>

                <div class="stat-card">
                    <div class="stat-value">{{ $tenantStats['regular_users'] ?? 0 }}</div>
                    <div class="stat-label">Utilisateurs</div>
                </div>

                <div class="stat-card">
                    <div class="stat-value">{{ $tenantStats['manager_users'] ?? 0 }}</div>
                    <div class="stat-label">Managers</div>
                </div>

                <div class="stat-card">
                    <div class="stat-value">{{ $tenantStats['total_personnes'] ?? 0 }}</div>
                    <div class="stat-label">Personnes</div>
                </div>

                <div class="stat-card">
                    <div class="stat-value">{{ $tenantStats['dashboard_configs'] ?? 0 }}</div>
                    <div class="stat-label">Configurations Dashboard</div>
                </div>
            </div>
        </div>

        <div class="section">
            <h2>Actions</h2>
            <div class="actions">
                <form method="POST" action="{{ route('admin.tenants.updateStatus', $tenant->id) }}" style="display: inline;">
                    @csrf
                    <input type="hidden" name="status" value="active">
                    <button type="submit" class="btn-action active" {{ $tenant->status === 'active' ? 'disabled' : '' }}>
                        Activer
                    </button>
                </form>

                <form method="POST" action="{{ route('admin.tenants.updateStatus', $tenant->id) }}" style="display: inline;">
                    @csrf
                    <input type="hidden" name="status" value="suspended">
                    <button type="submit" class="btn-action suspend" {{ $tenant->status === 'suspended' ? 'disabled' : '' }}>
                        Suspendre
                    </button>
                </form>

                <form method="POST" action="{{ route('admin.tenants.updateStatus', $tenant->id) }}" style="display: inline;">
                    @csrf
                    <input type="hidden" name="status" value="inactive">
                    <button type="submit" class="btn-action inactive" {{ $tenant->status === 'inactive' ? 'disabled' : '' }}>
                        Désactiver
                    </button>
                </form>

                @if($tenant->trashed())
                    <form method="POST" action="{{ route('admin.tenants.restore', $tenant->id) }}" style="display: inline;">
                        @csrf
                        <button type="submit" class="btn-action active">
                            Restaurer
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.tenants.destroy', $tenant->id) }}" 
                          style="display: inline;"
                          onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce tenant ? Cette action est irréversible.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-action danger">
                            Supprimer
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</body>
</html>

