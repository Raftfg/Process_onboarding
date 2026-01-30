@extends('admin.layouts.app')

@section('title', 'Gestion des Tenants')

@section('content')
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <h1 style="font-size: 28px; font-weight: 600;">Tenants</h1>
        
        <form method="GET" action="{{ route('admin.tenants.index') }}" style="display: flex; gap: 10px;">
            <input type="text" name="search" value="{{ $search }}" placeholder="Rechercher..." style="padding: 10px 15px; border: 2px solid var(--border-color); border-radius: 8px; font-size: 14px;">
            <button type="submit" class="btn btn-primary" style="padding: 10px 20px;">Rechercher</button>
            @if($search)
                <a href="{{ route('admin.tenants.index') }}" class="btn" style="padding: 10px 20px; background: #e5e7eb; color: #333;">Effacer</a>
            @endif
        </form>
    </div>

    <div class="card">
        @if($tenants->count() > 0)
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
                    @foreach($tenants as $tenant)
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
                                <a href="{{ route('admin.tenants.show', $tenant->id) }}" class="btn btn-primary" style="padding: 6px 12px; font-size: 12px; margin-right: 5px;">Voir</a>
                                <form method="POST" action="{{ route('admin.tenants.destroy', $tenant->id) }}" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce tenant ? Cette action est irréversible.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger" style="padding: 6px 12px; font-size: 12px;">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            
            <div style="margin-top: 20px;">
                {{ $tenants->links() }}
            </div>
        @else
            <p style="color: #666; text-align: center; padding: 40px;">Aucun tenant trouvé.</p>
        @endif
    </div>
@endsection
