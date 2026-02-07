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
            background: #00286f;
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
            background: #00286f;
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
            <h1>{{ trans('emails.welcome_title', ['brand' => config('app.brand_name')]) }}</h1>
        </div>
        <div class="content">
            <p>Bonjour {{ $adminName }},</p>
            
            <p>{{ trans('emails.welcome_message', ['brand' => config('app.brand_name')]) }}</p>
            
            <p><strong>Votre sous-domaine:</strong> {{ $subdomain }}</p>
            
            <p>{{ trans('emails.welcome_access') }}</p>
            
            <p><strong>Votre email de connexion:</strong> {{ $adminEmail ?? 'Utilisé lors de l\'onboarding' }}</p>
            
            <a href="{{ $url }}" class="button">{{ trans('emails.welcome_button') }}</a>
            
            <p style="margin-top: 20px; font-size: 12px; color: #666;">
                Si le bouton ne fonctionne pas, copiez et collez ce lien dans votre navigateur :<br>
                <a href="{{ $url }}" style="color: #667eea;">{{ $url }}</a>
            </p>
            
            <p style="margin-top: 30px;">Cordialement,<br>L'équipe {{ config('app.brand_name') }}</p>
        </div>
    </div>
</body>
</html>
