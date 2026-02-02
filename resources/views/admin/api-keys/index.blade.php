@extends('admin.layouts.app')

@section('title', 'Gestion des Clés API')

@section('content')
    <div class="card">
        <h3 style="margin-bottom: 20px;">Générer une nouvelle clé</h3>
        <form action="{{ route('admin.api-keys.store') }}" method="POST" style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 15px; align-items: end;">
            @csrf
            <div>
                <label style="display: block; margin-bottom: 5px; font-size: 14px;">Nom de l'application / Client</label>
                <input type="text" name="name" required placeholder="Ex: App Parent React" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 8px;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 5px; font-size: 14px;">Expiration (Optionnel)</label>
                <input type="date" name="expires_at" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 8px;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 5px; font-size: 14px;">Rate Limit (req/min)</label>
                <input type="number" name="rate_limit" value="100" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 8px;">
            </div>
            <button type="submit" class="btn btn-primary" style="height: 42px;">Générer</button>
        </form>
    </div>

    @if(session('new_api_key'))
        <div class="card" style="border: 2px solid #667eea; background: #f0f4ff;">
            <h3 style="color: #4c51bf; margin-bottom: 10px;">Nouvelle Clé API Générée</h3>
            <p style="margin-bottom: 15px;">Pour : <strong>{{ session('new_api_key_name') }}</strong></p>
            <div style="background: white; padding: 15px; border-radius: 8px; border: 1px dashed #667eea; font-family: monospace; font-size: 18px; margin-bottom: 15px; word-break: break-all;">
                {{ session('new_api_key') }}
            </div>
            <p style="color: #e53e3e; font-weight: 600;">IMPORTANT: Sauvegardez cette clé maintenant. Elle ne sera plus jamais affichée en clair !</p>
        </div>
    @endif

    <div class="card">
        <h3>Clés Actives</h3>
        @if($keys->count() > 0)
            <table style="margin-top: 20px;">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Préfixe</th>
                        <th>Status</th>
                        <th>Rate Limit</th>
                        <th>Expire le</th>
                        <th>Créée le</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($keys as $key)
                        <tr>
                            <td><strong>{{ $key->name }}</strong></td>
                            <td><code>{{ $key->key_prefix }}</code></td>
                            <td>
                                <span style="padding: 4px 10px; border-radius: 12px; font-size: 12px; background: {{ $key->is_active ? '#d1fae5' : '#fee2e2' }}; color: {{ $key->is_active ? '#065f46' : '#991b1b' }}">
                                    {{ $key->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>{{ $key->rate_limit }} / min</td>
                            <td>{{ $key->expires_at ? $key->expires_at->format('d/m/Y') : 'Jamais' }}</td>
                            <td>{{ $key->created_at->format('d/m/Y') }}</td>
                            <td>
                                <div style="display: flex; gap: 10px;">
                                    <form action="{{ route('admin.api-keys.toggle', $key->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn" style="padding: 6px 12px; font-size: 12px; background: #e2e8f0;">
                                            {{ $key->is_active ? 'Désactiver' : 'Activer' }}
                                        </button>
                                    </form>
                                    <form action="{{ route('admin.api-keys.destroy', $key->id) }}" method="POST" onsubmit="return confirm('Supprimer définitivement cette clé ?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger" style="padding: 6px 12px; font-size: 12px;">Supprimer</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p style="text-align: center; color: #666; padding: 40px;">Aucune clé API configurée.</p>
        @endif
    </div>
@endsection
