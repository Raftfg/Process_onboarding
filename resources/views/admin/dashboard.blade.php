<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - MedKey</title>
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

        .btn-secondary {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .container {
            max-width: 1400px;
            margin: 40px auto;
            padding: 0 30px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .stat-card h3 {
            font-size: 14px;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }

        .stat-card .value {
            font-size: 32px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .stat-card .label {
            font-size: 14px;
            color: #666;
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

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }

        .table th {
            font-weight: 600;
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table td {
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

        .btn-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .btn-link:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .table {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>🔧 Administration MedKey</h1>
            <div class="header-actions">
                <a href="{{ route('admin.tenants.index') }}" class="btn btn-secondary">Gérer les Tenants</a>
                <form method="POST" action="{{ route('admin.logout') }}" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn btn-secondary">Déconnexion</button>
                </form>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Tenants</h3>
                <div class="value">{{ $stats['total_tenants'] }}</div>
                <div class="label">Tous les tenants</div>
            </div>

            <div class="stat-card">
                <h3>Actifs</h3>
                <div class="value" style="color: #10b981;">{{ $stats['active_tenants'] }}</div>
                <div class="label">Tenants actifs</div>
            </div>

            <div class="stat-card">
                <h3>Suspendus</h3>
                <div class="value" style="color: #ef4444;">{{ $stats['suspended_tenants'] }}</div>
                <div class="label">Tenants suspendus</div>
            </div>

            <div class="stat-card">
                <h3>Inactifs</h3>
                <div class="value" style="color: #6b7280;">{{ $stats['inactive_tenants'] }}</div>
                <div class="label">Tenants inactifs</div>
            </div>
        </div>

        <div class="section">
            <h2>Tenants récents</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Sous-domaine</th>
                        <th>Statut</th>
                        <th>Date de création</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($stats['recent_tenants'] as $tenant)
                        <tr>
                            <td>{{ $tenant->name }}</td>
                            <td><code>{{ $tenant->subdomain }}</code></td>
                            <td>
                                <span class="status-badge {{ $tenant->status }}">
                                    {{ ucfirst($tenant->status) }}
                                </span>
                            </td>
                            <td>{{ $tenant->created_at->format('d/m/Y H:i') }}</td>
                            <td>
                                <a href="{{ route('admin.tenants.show', $tenant->id) }}" class="btn-link">Voir</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; color: #999; padding: 40px;">
                                Aucun tenant trouvé
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>

