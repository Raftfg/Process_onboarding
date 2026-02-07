<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Applications - Onboarding Service</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .header {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 30px;
            margin-bottom: 30px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .search-form {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        input[type="email"] {
            flex: 1;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        input[type="email"]:focus {
            outline: none;
            border-color: #667eea;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        .btn-secondary {
            background: #f0f0f0;
            color: #333;
        }
        .btn-secondary:hover {
            background: #e0e0e0;
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .applications-list {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 30px;
        }
        .application-card {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        .application-card:hover {
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        }
        .application-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        .application-info h3 {
            color: #333;
            margin-bottom: 5px;
            font-size: 18px;
        }
        .application-info p {
            color: #666;
            font-size: 14px;
        }
        .application-id {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            color: #999;
            background: #f5f5f5;
            padding: 4px 8px;
            border-radius: 4px;
        }
        .application-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        .empty-state h3 {
            margin-bottom: 10px;
            color: #333;
        }
        .empty-state p {
            margin-bottom: 20px;
        }
        .link {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        .link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Mes Applications</h1>
            <p class="subtitle">Recherchez vos applications enregistrées par email</p>
            
            @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-error">
                    {{ session('error') }}
                </div>
            @endif

            <form method="GET" action="{{ route('applications.index') }}" class="search-form">
                <input 
                    type="email" 
                    name="email" 
                    placeholder="Entrez votre email de contact..." 
                    value="{{ $searchEmail ?? '' }}"
                    required
                >
                <button type="submit" class="btn btn-primary">Rechercher</button>
                @if($searchEmail)
                    <a href="{{ route('applications.index') }}" class="btn btn-secondary">Réinitialiser</a>
                @endif
            </form>

            <div style="text-align: center; margin-top: 20px;">
                <a href="{{ route('applications.register.form') }}" class="link">Enregistrer une nouvelle application →</a>
            </div>
        </div>

        <div class="applications-list">
            @if($searchEmail)
                @if($applications->isEmpty())
                    <div class="empty-state">
                        <h3>Aucune application trouvée</h3>
                        <p>Aucune application n'a été trouvée pour l'email <strong>{{ $searchEmail }}</strong>.</p>
                        <p>Vérifiez votre email ou <a href="{{ route('applications.register.form') }}" class="link">enregistrez une nouvelle application</a>.</p>
                    </div>
                @else
                    <h2 style="margin-bottom: 20px; color: #333;">Applications trouvées ({{ $applications->count() }})</h2>
                    @foreach($applications as $app)
                        <div class="application-card">
                            <div class="application-header">
                                <div class="application-info">
                                    <h3>{{ $app->display_name }}</h3>
                                    <p>{{ $app->contact_email }}</p>
                                    <div class="application-id" style="margin-top: 8px;">
                                        ID: {{ $app->app_id }}
                                    </div>
                                </div>
                                <div>
                                    @if($app->is_active)
                                        <span style="background: #d4edda; color: #155724; padding: 4px 12px; border-radius: 4px; font-size: 12px; font-weight: 600;">
                                            Active
                                        </span>
                                    @else
                                        <span style="background: #f8d7da; color: #721c24; padding: 4px 12px; border-radius: 4px; font-size: 12px; font-weight: 600;">
                                            Inactive
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="application-actions">
                                <a href="{{ route('applications.dashboard', ['app_id' => $app->app_id]) }}" class="btn btn-primary">
                                    Accéder au Dashboard
                                </a>
                                <a href="{{ route('applications.api-keys', ['app_id' => $app->app_id]) }}" class="btn btn-secondary">
                                    Gérer les Clés API
                                </a>
                            </div>
                        </div>
                    @endforeach
                @endif
            @else
                <div class="empty-state">
                    <h3>Recherchez vos applications</h3>
                    <p>Entrez votre email de contact pour voir vos applications enregistrées.</p>
                </div>
            @endif
        </div>
    </div>
</body>
</html>
