@extends('admin.layouts.app')

@section('title', 'Détails du Tenant')

@section('content')
    <div style="margin-bottom: 20px;">
        <a href="{{ route('admin.tenants.index') }}" class="btn" style="background: #e5e7eb; color: #333; padding: 8px 16px;">← Retour</a>
    </div>

    <div class="card">
        <h2 style="margin-bottom: 20px; font-size: 24px; font-weight: 600;">{{ $tenant->hospital_name }}</h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div>
                <strong style="color: #666; font-size: 12px; text-transform: uppercase;">Sous-domaine</strong>
                <div style="margin-top: 5px; font-size: 16px;"><code>{{ $tenant->subdomain }}</code></div>
            </div>
            
            <div>
                <strong style="color: #666; font-size: 12px; text-transform: uppercase;">Base de données</strong>
                <div style="margin-top: 5px; font-size: 16px;"><code>{{ $tenant->database_name }}</code></div>
            </div>
            
            <div>
                <strong style="color: #666; font-size: 12px; text-transform: uppercase;">Email Admin</strong>
                <div style="margin-top: 5px; font-size: 16px;">{{ $tenant->admin_email }}</div>
            </div>
            
            <div>
                <strong style="color: #666; font-size: 12px; text-transform: uppercase;">Créé le</strong>
                <div style="margin-top: 5px; font-size: 16px;">{{ $tenant->created_at->format('d/m/Y à H:i') }}</div>
            </div>
        </div>

        @if($tenant->hospital_address)
            <div style="margin-bottom: 20px;">
                <strong style="color: #666; font-size: 12px; text-transform: uppercase;">Adresse</strong>
                <div style="margin-top: 5px;">{{ $tenant->hospital_address }}</div>
            </div>
        @endif
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <h3>Utilisateurs Totaux</h3>
            <div class="value">{{ $stats['total_users'] }}</div>
        </div>
        
        <div class="stat-card">
            <h3>Utilisateurs Actifs</h3>
            <div class="value">{{ $stats['active_users'] }}</div>
        </div>
        
        <div class="stat-card">
            <h3>Utilisateurs Inactifs</h3>
            <div class="value">{{ $stats['inactive_users'] }}</div>
        </div>
        
        <div class="stat-card">
            <h3>Activités</h3>
            <div class="value">{{ $stats['total_activities'] }}</div>
        </div>
        
        <div class="stat-card">
            <h3>Notifications</h3>
            <div class="value">{{ $stats['total_notifications'] }}</div>
        </div>
    </div>

    <div class="card">
        <h2 style="margin-bottom: 20px; font-size: 20px; font-weight: 600;">Utilisateurs du Tenant</h2>
        
        @if(count($users) > 0)
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Email</th>
                        <th>Rôle</th>
                        <th>Statut</th>
                        <th>Créé le</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                        <tr>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>
                                <span style="padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; background: {{ $user->role === 'admin' ? 'rgba(102, 126, 234, 0.1)' : 'rgba(156, 163, 175, 0.1)' }}; color: {{ $user->role === 'admin' ? 'var(--primary-color)' : '#666' }};">
                                    {{ ucfirst($user->role) }}
                                </span>
                            </td>
                            <td>
                                <span style="padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; background: {{ $user->status === 'active' ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)' }}; color: {{ $user->status === 'active' ? '#10b981' : '#ef4444' }};">
                                    {{ ucfirst($user->status) }}
                                </span>
                            </td>
                            <td>{{ \Carbon\Carbon::parse($user->created_at)->format('d/m/Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p style="color: #666; text-align: center; padding: 40px;">Aucun utilisateur trouvé.</p>
        @endif
    </div>

    <div class="card" style="background: #fee2e2; border: 1px solid #fca5a5;">
        <h3 style="color: #991b1b; margin-bottom: 15px;">Zone de danger</h3>
        <p style="color: #991b1b; margin-bottom: 15px;">La suppression d'un tenant supprimera également sa base de données. Cette action est irréversible.</p>
        <form method="POST" action="{{ route('admin.tenants.destroy', $tenant->id) }}" onsubmit="return confirm('Êtes-vous ABSOLUMENT sûr de vouloir supprimer ce tenant et sa base de données ? Cette action est IRRÉVERSIBLE.');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger">Supprimer ce tenant</button>
        </form>
    </div>
@endsection
