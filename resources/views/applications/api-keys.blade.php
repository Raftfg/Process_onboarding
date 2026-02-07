<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Clés API - {{ $application->display_name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f5f5;
            color: #333;
        }
        .header {
            background: white;
            border-bottom: 1px solid #e0e0e0;
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 { font-size: 24px; color: #333; }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .card h2 {
            font-size: 18px;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        th {
            font-weight: 600;
            color: #666;
            font-size: 14px;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-active { background: #d4edda; color: #155724; }
        .badge-inactive { background: #f8d7da; color: #721c24; }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            border: none;
            cursor: pointer;
        }
        .btn-secondary {
            background: #6c757d;
        }
        code {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Gestion des Clés API - {{ $application->display_name }}</h1>
        <div>
            <a href="{{ route('applications.dashboard', $application->app_id) }}" class="btn btn-secondary">Retour au dashboard</a>
        </div>
    </div>

    <div class="container">
        <div class="card">
            <h2>Clés API</h2>
            @if($apiKeys->count() > 0)
                <table>
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Préfixe</th>
                            <th>Statut</th>
                            <th>Rate Limit</th>
                            <th>Créée le</th>
                            <th>Dernière utilisation</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($apiKeys as $key)
                            <tr>
                                <td>{{ $key->name }}</td>
                                <td><code>{{ $key->key_prefix }}...</code></td>
                                <td>
                                    <span class="badge badge-{{ $key->is_active ? 'active' : 'inactive' }}">
                                        {{ $key->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>{{ $key->rate_limit }} / min</td>
                                <td>{{ $key->created_at->format('d/m/Y H:i') }}</td>
                                <td>{{ $key->last_used_at ? $key->last_used_at->format('d/m/Y H:i') : 'Jamais' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p>Aucune clé API pour le moment. Utilisez l'API pour en créer une.</p>
            @endif
        </div>

        <div class="card">
            <h2>Créer une nouvelle clé API</h2>
            
            @if(session('new_api_key'))
                <div class="alert alert-success" style="margin-bottom: 20px;">
                    <strong>✅ Clé API créée avec succès !</strong>
                    <p style="margin-top: 10px;">
                        <strong>⚠️ IMPORTANT :</strong> Sauvegardez cette clé immédiatement, elle ne sera plus jamais affichée.
                    </p>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin-top: 10px; word-break: break-all;">
                        <code style="font-size: 14px;">{{ session('new_api_key') }}</code>
                    </div>
                    <button onclick="copyToClipboard('{{ session('new_api_key') }}')" class="btn" style="margin-top: 10px;">📋 Copier la clé</button>
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-error" style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                    <ul style="margin: 0; padding-left: 20px;">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form id="createApiKeyForm" method="POST" action="{{ route('applications.api-keys.store', $application->app_id) }}" style="display: grid; gap: 15px;">
                @csrf
                
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Nom de la clé *</label>
                    <input type="text" name="name" required placeholder="Ex: Production Key, Development Key" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                    <p style="font-size: 12px; color: #666; margin-top: 5px;">Un nom descriptif pour identifier cette clé</p>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">Rate Limit (req/min)</label>
                        <input type="number" name="rate_limit" value="100" min="1" max="10000" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        <p style="font-size: 12px; color: #666; margin-top: 5px;">Limite de requêtes par minute (1-10000)</p>
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">Date d'expiration (optionnel)</label>
                        <input type="date" name="expires_at" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        <p style="font-size: 12px; color: #666; margin-top: 5px;">La clé expirera automatiquement à cette date</p>
                    </div>
                </div>

                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Master Key *</label>
                    <input type="password" name="master_key" required placeholder="Votre master key (mk_live_...)" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                    <p style="font-size: 12px; color: #666; margin-top: 5px;">Votre master key est nécessaire pour créer une clé API</p>
                </div>

                <button type="submit" class="btn" style="padding: 12px 24px; font-size: 16px;">Créer la clé API</button>
            </form>

            <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 6px;">
                <p style="font-size: 14px; color: #666; margin: 0;">
                    <strong>💡 Alternative :</strong> Vous pouvez aussi créer une clé API via l'API REST :
                    <code style="display: block; margin-top: 5px;">POST /api/v1/applications/{{ $application->app_id }}/api-keys</code>
                </p>
            </div>
        </div>
    </div>

    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('Clé API copiée dans le presse-papiers !');
            }, function(err) {
                alert('Erreur lors de la copie. Veuillez copier manuellement.');
            });
        }
    </script>
</body>
</html>
