<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sélectionnez votre espace - Akasi Group</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #00286f;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            width: 100%;
            max-width: 600px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo {
            margin-bottom: 20px;
        }

        .logo h1 {
            font-size: 2.5rem;
            font-weight: 700;
            color: #00286f;
            margin: 0;
        }

        .header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .header p {
            color: #666;
            font-size: 14px;
        }

        .email-info {
            background: #f3f4f6;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 25px;
            text-align: center;
        }

        .email-info strong {
            color: #667eea;
        }

        .subdomains-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 25px;
        }

        .subdomain-item {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            transition: all 0.3s;
            background: white;
        }

        .subdomain-item:hover {
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
            transform: translateY(-2px);
        }

        .subdomain-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .subdomain-name {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }

        .subdomain-badge {
            background: #667eea;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .subdomain-badge.admin {
            background: #10b981;
        }

        .subdomain-badge.user {
            background: #6366f1;
        }

        .subdomain-info {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
        }

        .btn-connect {
            width: 100%;
            padding: 12px;
            background: #00286f;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn-connect:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
        }

        .btn-connect:active {
            transform: translateY(0);
        }

        .no-subdomains {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }

        .no-subdomains h2 {
            color: #333;
            margin-bottom: 10px;
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
        }

        .back-link a:hover {
            text-decoration: underline;
        }

        .error-message {
            background: #fee2e2;
            color: #dc2626;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">
                <h1>Akasi Group</h1>
            </div>
            <h1>Sélectionnez votre espace</h1>
            <p>Choisissez l'espace auquel vous souhaitez vous connecter</p>
        </div>

        <div class="email-info">
            Email : <strong>{{ $email }}</strong>
        </div>

        @if($errors->has('subdomain'))
            <div class="error-message">
                {{ $errors->first('subdomain') }}
            </div>
        @endif

        @if(empty($subdomains))
            <div class="no-subdomains">
                <h2>Aucun espace trouvé</h2>
                <p>Aucun espace n'a été trouvé pour cet email.</p>
                <div class="back-link" style="margin-top: 30px;">
                    <a href="{{ route('root.login') }}">← Retour au formulaire</a>
                </div>
            </div>
        @else
            <div class="subdomains-list">
                @foreach($subdomains as $subdomain)
                    <div class="subdomain-item">
                        <div class="subdomain-header">
                            <div class="subdomain-name">{{ $subdomain['subdomain'] }}</div>
                            @if($subdomain['user_role'])
                                <span class="subdomain-badge {{ $subdomain['user_role'] }}">
                                    {{ ucfirst($subdomain['user_role']) }}
                                </span>
                            @endif
                        </div>
                        <div class="subdomain-info">
                            Organisation : <strong>{{ $subdomain['organization_name'] }}</strong>
                        </div>
                        <form method="POST" action="{{ route('root.login.select') }}" style="margin: 0;">
                            @csrf
                            <input type="hidden" name="subdomain" value="{{ $subdomain['subdomain'] }}">
                            <button type="submit" class="btn-connect">
                                Se connecter à cet espace
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>

            <div class="back-link">
                <a href="{{ route('root.login') }}">← Utiliser un autre email</a>
            </div>
        @endif
    </div>
</body>
</html>
