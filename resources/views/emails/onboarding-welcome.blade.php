<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 10px 10px 0 0;
        }
        .content {
            background: #f9f9f9;
            padding: 30px;
            border-radius: 0 0 10px 10px;
        }
        .button {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Bienvenue sur MedKey !</h1>
        </div>
        <div class="content">
            <p>Bonjour {{ $adminName }},</p>
            
            <p>Votre compte MedKey a été créé avec succès !</p>
            
            <p><strong>Votre sous-domaine:</strong> {{ $subdomain }}</p>
            
            <p>Vous pouvez maintenant accéder à votre espace d'administration en cliquant sur le bouton ci-dessous :</p>
            
            <p><strong>Votre email de connexion:</strong> {{ $adminEmail ?? 'Utilisé lors de l\'onboarding' }}</p>
            
            <a href="{{ $url }}" class="button">Se connecter à mon espace</a>
            
            <p style="margin-top: 20px; font-size: 12px; color: #666;">
                Si le bouton ne fonctionne pas, copiez et collez ce lien dans votre navigateur :<br>
                <a href="{{ $url }}" style="color: #667eea;">{{ $url }}</a>
            </p>
            
            <p style="margin-top: 30px;">Cordialement,<br>L'équipe MedKey</p>
        </div>
    </div>
</body>
</html>
