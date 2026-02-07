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
            <p>Pour créer une nouvelle clé API, utilisez l'endpoint API :</p>
            <code>POST /api/v1/applications/{{ $application->app_id }}/api-keys</code>
            <p style="margin-top: 10px; font-size: 14px; color: #666;">
                Vous devez utiliser votre master key dans le header <code>X-Master-Key</code>
            </p>
        </div>
    </div>
</body>
</html>
