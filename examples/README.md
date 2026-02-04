# Exemples d'Intégration

Ce dossier contient des scripts pour tester et comprendre l'intégration.

## `simulate_client_app.php`
Ce script PHP simule votre back-office (ex: Laravel, Node, Symfony) qui appelle le microservice.

### Comment l'utiliser ?

1. Assurez-vous que le serveur tourne : `php artisan serve --port=8000`
2. Ouvrez `simulate_client_app.php` et modifiez :
   - `$apiKey` : Mettez une clé valide générée dans `/admin/api-keys`
   - `$appName` : Mettez le nom d'application associé à cette clé
3. Lancez le script :
   ```bash
   php examples/simulate_client_app.php
   ```

Si tout fonctionne, vous verrez l'URL du nouveau tenant généré.
