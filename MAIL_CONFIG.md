# Configuration de l'envoi d'emails

## Configuration dans .env

Pour que l'envoi d'emails fonctionne, vous devez configurer les variables suivantes dans votre fichier `.env` :

### Option 1 : Utiliser Mailtrap (pour le développement)
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=votre_username_mailtrap
MAIL_PASSWORD=votre_password_mailtrap
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@akasigroup.local
MAIL_FROM_NAME="Akasi Group"
```

### Option 2 : Utiliser Gmail (pour le développement)
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=votre_email@gmail.com
MAIL_PASSWORD=votre_mot_de_passe_application
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=votre_email@gmail.com
MAIL_FROM_NAME="Akasi Group"
```

### Option 3 : Utiliser le driver log (pour le développement - les emails sont écrits dans les logs)
```env
MAIL_MAILER=log
MAIL_FROM_ADDRESS=noreply@akasigroup.local
MAIL_FROM_NAME="Akasi Group"
```

### Option 4 : Utiliser SendGrid (pour la production)
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=votre_api_key_sendgrid
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@votre-domaine.com
MAIL_FROM_NAME="Akasi Group"
```

## Vérification

Après avoir configuré les variables dans `.env`, exécutez :

```bash
php artisan config:clear
php artisan config:cache
```

## Test de l'envoi d'email

Pour tester l'envoi d'email, vous pouvez :

1. Créer un nouvel onboarding via l'interface web
2. L'email de bienvenue sera automatiquement envoyé à l'adresse email de l'administrateur
3. Vérifier les logs dans `storage/logs/laravel.log` pour voir si l'email a été envoyé

## Dépannage

Si les emails ne sont pas envoyés :

1. Vérifiez que les variables `MAIL_*` sont bien définies dans `.env`
2. Vérifiez les logs dans `storage/logs/laravel.log` pour voir les erreurs
3. Si vous utilisez SMTP, vérifiez que les identifiants sont corrects
4. Si vous utilisez Gmail, vous devez activer "Accès des applications moins sécurisées" ou utiliser un mot de passe d'application
