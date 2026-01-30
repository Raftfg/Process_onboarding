<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenue - MedKey</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            max-width: 600px;
            width: 100%;
        }

        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 50px;
            text-align: center;
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .success-icon {
            width: 100px;
            height: 100px;
            margin: 0 auto 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 50px;
            color: white;
        }

        h1 {
            font-size: 2.5rem;
            color: #333;
            margin-bottom: 20px;
        }

        .subdomain {
            display: inline-block;
            padding: 10px 20px;
            background: #f0f0f0;
            border-radius: 10px;
            font-size: 1.2rem;
            font-weight: 600;
            color: #667eea;
            margin: 20px 0;
        }

        p {
            color: #666;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .btn {
            display: inline-block;
            padding: 14px 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            margin-top: 30px;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            @if($isWelcome)
                <div class="success-icon">✓</div>
                <h1>Bienvenue sur MedKey !</h1>
                <p>Votre compte a été créé avec succès.</p>
                <div class="subdomain">{{ $subdomain }}</div>
                <p>Vous pouvez maintenant commencer à utiliser votre espace d'administration.</p>
            @else
                <h1>Bienvenue</h1>
                <p>Vous êtes sur votre sous-domaine MedKey.</p>
                <div class="subdomain">{{ $subdomain }}</div>
            @endif
            
            @auth
                <a href="{{ subdomain_url($subdomain, '/dashboard') }}" class="btn">Accéder au tableau de bord</a>
            @else
                <a href="{{ subdomain_url($subdomain, '/login') }}" class="btn">Accéder à mon espace</a>
                <p style="margin-top: 20px;">
                    <a href="{{ route('start') }}" style="color: #667eea; text-decoration: none; font-size: 14px;">
                        Se connecter à un autre domaine
                    </a>
                </p>
            @endauth
        </div>
    </div>
</body>
</html>
