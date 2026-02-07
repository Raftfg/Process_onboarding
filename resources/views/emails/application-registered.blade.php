<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Application Enregistrée</title>
</head>
<body>
    <h1>Votre application a été enregistrée avec succès</h1>
    
    <p>Bonjour,</p>
    
    <p>Votre application <strong>{{ $display_name }}</strong> ({{ $app_name }}) a été enregistrée avec succès.</p>
    
    <h2>Master Key</h2>
    <p><strong>⚠️ IMPORTANT:</strong> Sauvegardez cette master key immédiatement ! Elle ne sera plus jamais affichée.</p>
    <p style="background: #f8f9fa; padding: 15px; border: 2px dashed #667eea; font-family: monospace; word-break: break-all;">
        {{ $master_key }}
    </p>
    
    @if($database_created && $database)
        <h2>Base de données</h2>
        <p><strong>⚠️ IMPORTANT:</strong> Sauvegardez ces credentials ! Le mot de passe ne sera plus jamais affiché.</p>
        <ul>
            <li><strong>Nom:</strong> {{ $database['name'] }}</li>
            <li><strong>Host:</strong> {{ $database['host'] }}</li>
            <li><strong>Port:</strong> {{ $database['port'] }}</li>
            <li><strong>Username:</strong> {{ $database['username'] }}</li>
            <li><strong>Password:</strong> {{ $database['password'] }}</li>
        </ul>
    @else
        <p>La création de la base de données a échoué. Vous pouvez réessayer avec l'API.</p>
    @endif
    
    <h2>Prochaines étapes</h2>
    <p>Vous pouvez maintenant utiliser votre master key pour :</p>
    <ul>
        <li>Démarrer des onboardings : <code>POST /api/v1/onboarding/start</code></li>
        <li>Provisionner l'infrastructure : <code>POST /api/v1/onboarding/provision</code></li>
        <li>Vérifier le statut : <code>GET /api/v1/onboarding/status/{uuid}</code></li>
    </ul>
    
    <p>Consultez la documentation pour plus d'informations.</p>
    
    <p>Cordialement,<br>L'équipe Onboarding Service</p>
</body>
</html>
