<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Tenants - MedKey Admin</title>
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

        .filters {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-size: 12px;
            color: #666;
            font-weight: 600;
        }

        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }

        .btn-filter {
            padding: 8px 20px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }

        .table-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow-x: auto;
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
            margin-right: 10px;
        }

        .btn-link:hover {
            text-decoration: underline;
        }

        .btn-link.danger {
            color: #ef4444;
        }

        .pagination {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 10px;
        }

        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            text-decoration: none;
            color: #333;
        }

        .pagination .active {
            background: #667eea;
            color: white;
            border-color: #667eea;
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
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>🏢 Gestion des Tenants</h1>
            <div class="header-actions">
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

        <div class="filters">
            <form method="GET" action="{{ route('admin.tenants.index') }}" style="display: flex; gap: 15px; flex-wrap: wrap; width: 100%;">
                <div class="filter-group">
                    <label>Recherche</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Nom, sous-domaine, email...">
                </div>

                <div class="filter-group">
                    <label>Statut</label>
                    <select name="status">
                        <option value="">Tous</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Actif</option>
                        <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>Suspendu</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactif</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Tri</label>
                    <select name="sort_by">
                        <option value="created_at" {{ request('sort_by') === 'created_at' ? 'selected' : '' }}>Date de création</option>
                        <option value="name" {{ request('sort_by') === 'name' ? 'selected' : '' }}>Nom</option>
                        <option value="status" {{ request('sort_by') === 'status' ? 'selected' : '' }}>Statut</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn-filter">Filtrer</button>
                </div>
            </form>
        </div>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nom</th>
                        <th>Sous-domaine</th>
                        <th>Email</th>
                        <th>Statut</th>
                        <th>Plan</th>
                        <th>Créé le</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tenants as $tenant)
                        <tr>
                            <td>{{ $tenant->id }}</td>
                            <td>{{ $tenant->name }}</td>
                            <td><code>{{ $tenant->subdomain }}</code></td>
                            <td>{{ $tenant->email ?? 'N/A' }}</td>
                            <td>
                                <span class="status-badge {{ $tenant->status }}">
                                    {{ ucfirst($tenant->status) }}
                                </span>
                            </td>
                            <td>{{ $tenant->plan ?? 'N/A' }}</td>
                            <td>{{ $tenant->created_at->format('d/m/Y H:i') }}</td>
                            <td>
                                <a href="{{ route('admin.tenants.show', $tenant->id) }}" class="btn-link">Voir</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; color: #999; padding: 40px;">
                                Aucun tenant trouvé
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if($tenants->hasPages())
                <div class="pagination">
                    {{ $tenants->links() }}
                </div>
            @endif
        </div>
    </div>
</body>
</html>

