@extends('admin.layouts.app')

@section('title', 'Dashboard Administrateur')

@section('content')
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Tenants</h3>
            <div class="value">{{ $stats['total_tenants'] }}</div>
        </div>
        
        <div class="stat-card">
            <h3>Tenants Actifs</h3>
            <div class="value">{{ $stats['active_tenants'] }}</div>
        </div>
        
        <div class="stat-card">
            <h3>Tenants Inactifs</h3>
            <div class="value">{{ $stats['inactive_tenants'] }}</div>
        </div>
        
        <div class="stat-card">
            <h3>Utilisateurs Totaux</h3>
            <div class="value">{{ $stats['total_users'] }}</div>
        </div>
        
        <div class="stat-card">
            <h3>Activités Totales</h3>
            <div class="value">{{ $stats['total_activities'] }}</div>
        </div>
    </div>

    <div class="card">
        <h2 style="margin-bottom: 20px; font-size: 20px; font-weight: 600;">Derniers Tenants Créés</h2>
        
        @if($recentTenants->count() > 0)
            <table>
                <thead>
                    <tr>
                        <th>Hôpital</th>
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
                                <strong>{{ $tenant->hospital_name }}</strong>
                            </td>
                            <td>
                                <code>{{ $tenant->subdomain }}</code>
                            </td>
                            <td>{{ $tenant->admin_email }}</td>
                            <td>
                                <code style="font-size: 12px;">{{ $tenant->database_name }}</code>
                            </td>
                            <td>{{ $tenant->created_at->format('d/m/Y H:i') }}</td>
                            <td>
                                <a href="{{ route('admin.tenants.show', $tenant->id) }}" class="btn btn-primary" style="padding: 6px 12px; font-size: 12px;">Voir</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p style="color: #666; text-align: center; padding: 40px;">Aucun tenant créé pour le moment.</p>
        @endif
    </div>
@endsection
