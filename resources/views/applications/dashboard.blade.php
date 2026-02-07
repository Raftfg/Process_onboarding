<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - {{ $application->display_name }}</title>
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
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
        }
        .alert-success {
            background: #d4edda;
            border: 1px solid #28a745;
            color: #155724;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-card h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }
        .stat-card .value {
            font-size: 32px;
            font-weight: 600;
            color: #333;
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
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-activated { background: #d4edda; color: #155724; }
        .badge-cancelled { background: #f8d7da; color: #721c24; }
        .master-key-box {
            background: #f8f9fa;
            border: 2px dashed #667eea;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .master-key-box code {
            background: white;
            padding: 10px;
            display: block;
            font-family: monospace;
            word-break: break-all;
            margin-top: 10px;
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $application->display_name }}</h1>
        <div>
            <a href="{{ route('applications.api-keys', $application->app_id) }}" class="btn">Gérer les clés API</a>
        </div>
    </div>

    <div class="container">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if($masterKey)
            <div class="alert alert-warning">
                <strong>⚠️ IMPORTANT:</strong> Sauvegardez votre master key immédiatement ! Elle ne sera plus jamais affichée.
            </div>
            <div class="master-key-box">
                <strong>Master Key:</strong>
                <code>{{ $masterKey }}</code>
            </div>
        @endif

        @if($database)
            <div class="alert alert-warning">
                <strong>⚠️ IMPORTANT:</strong> Sauvegardez les credentials de la base de données ! Le mot de passe ne sera plus jamais affiché.
            </div>
            <div class="master-key-box">
                <strong>Base de données:</strong>
                <p><strong>Nom:</strong> {{ $database['name'] }}</p>
                <p><strong>Host:</strong> {{ $database['host'] }}</p>
                <p><strong>Port:</strong> {{ $database['port'] }}</p>
                <p><strong>Username:</strong> {{ $database['username'] }}</p>
                <p><strong>Password:</strong> <code>{{ $database['password'] }}</code></p>
            </div>
        @endif

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Onboardings</h3>
                <div class="value">{{ $stats['total'] }}</div>
            </div>
            <div class="stat-card">
                <h3>En attente</h3>
                <div class="value">{{ $stats['pending'] }}</div>
            </div>
            <div class="stat-card">
                <h3>Activés</h3>
                <div class="value">{{ $stats['activated'] }}</div>
            </div>
            <div class="stat-card">
                <h3>Annulés</h3>
                <div class="value">{{ $stats['cancelled'] }}</div>
            </div>
        </div>

        <div class="card">
            <h2>Onboardings récents</h2>
            @if($onboardings->count() > 0)
                <table>
                    <thead>
                        <tr>
                            <th>UUID</th>
                            <th>Email</th>
                            <th>Organisation</th>
                            <th>Sous-domaine</th>
                            <th>Statut</th>
                            <th>Créé le</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($onboardings as $onboarding)
                            <tr>
                                <td><code style="font-size: 11px;">{{ substr($onboarding->uuid, 0, 8) }}...</code></td>
                                <td>{{ $onboarding->email }}</td>
                                <td>{{ $onboarding->organization_name }}</td>
                                <td>{{ $onboarding->subdomain }}</td>
                                <td>
                                    <span class="badge badge-{{ $onboarding->status }}">
                                        {{ $onboarding->status }}
                                    </span>
                                </td>
                                <td>{{ $onboarding->created_at->format('d/m/Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                {{ $onboardings->links() }}
            @else
                <p>Aucun onboarding pour le moment.</p>
            @endif
        </div>
    </div>
</body>
</html>
