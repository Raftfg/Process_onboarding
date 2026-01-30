@extends('layouts.dashboard')

@section('title', 'Gestion des utilisateurs')

@section('content')
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <h1 style="font-size: 28px; font-weight: 600;">Utilisateurs</h1>
        <a href="{{ route('dashboard.users.create') }}" style="background: var(--primary-color); color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600;">
            + Nouvel utilisateur
        </a>
    </div>

    <div class="card">
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color);">
                        <th style="padding: 15px; text-align: left; font-weight: 600;">Nom</th>
                        <th style="padding: 15px; text-align: left; font-weight: 600;">Email</th>
                        <th style="padding: 15px; text-align: left; font-weight: 600;">Rôle</th>
                        <th style="padding: 15px; text-align: left; font-weight: 600;">Statut</th>
                        <th style="padding: 15px; text-align: left; font-weight: 600;">Créé le</th>
                        <th style="padding: 15px; text-align: right; font-weight: 600;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 15px;">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--primary-color); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600;">
                                        {{ strtoupper(substr($user->name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <div style="font-weight: 600;">{{ $user->name }}</div>
                                        @if($user->phone)
                                            <div style="font-size: 12px; color: #666;">{{ $user->phone }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td style="padding: 15px;">{{ $user->email }}</td>
                            <td style="padding: 15px;">
                                <span style="padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; background: {{ $user->role === 'admin' ? 'rgba(102, 126, 234, 0.1)' : 'rgba(156, 163, 175, 0.1)' }}; color: {{ $user->role === 'admin' ? 'var(--primary-color)' : '#666' }};">
                                    {{ ucfirst($user->role) }}
                                </span>
                            </td>
                            <td style="padding: 15px;">
                                <span style="padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; background: {{ $user->status === 'active' ? '#d1fae5' : '#fee2e2' }}; color: {{ $user->status === 'active' ? '#065f46' : '#991b1b' }};">
                                    {{ $user->status === 'active' ? 'Actif' : 'Inactif' }}
                                </span>
                            </td>
                            <td style="padding: 15px; color: #666; font-size: 14px;">
                                {{ $user->created_at->format('d/m/Y') }}
                            </td>
                            <td style="padding: 15px; text-align: right;">
                                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                                    <a href="{{ route('dashboard.users.edit', $user->id) }}" style="padding: 6px 12px; background: var(--bg-color); border-radius: 6px; text-decoration: none; color: var(--text-color); font-size: 14px;">
                                        Modifier
                                    </a>
                                    @if($user->id !== Auth::id())
                                        <form action="{{ route('dashboard.users.destroy', $user->id) }}" method="POST" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" style="padding: 6px 12px; background: #fee2e2; color: #991b1b; border: none; border-radius: 6px; cursor: pointer; font-size: 14px;">
                                                Supprimer
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="padding: 40px; text-align: center; color: #999;">
                                Aucun utilisateur trouvé
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border-color);">
            {{ $users->links() }}
        </div>
    </div>
@endsection
