# Configuration reCAPTCHA

## Installation

Le package `google/recaptcha` a été installé via Composer.

## Configuration

1. Obtenez vos clés reCAPTCHA depuis [Google reCAPTCHA Admin](https://www.google.com/recaptcha/admin)

2. **IMPORTANT** : Lors de la création de votre site reCAPTCHA, ajoutez les domaines suivants dans la liste des domaines autorisés :
   - `localhost`
   - `127.0.0.1`
   - `*.localhost` (pour les sous-domaines locaux)
   - Votre domaine de production (ex: `medkey.com`, `*.medkey.com`)

3. Ajoutez les clés dans votre fichier `.env` :

```env
RECAPTCHA_SITE_KEY=votre_site_key
RECAPTCHA_SECRET_KEY=votre_secret_key
RECAPTCHA_VERSION=v2
RECAPTCHA_ENABLED=true
```

4. Pour reCAPTCHA v3, changez `RECAPTCHA_VERSION=v3`

## Désactivation en développement local

Si vous rencontrez des problèmes avec reCAPTCHA en local, vous pouvez :

1. **Désactiver complètement reCAPTCHA** en ajoutant dans `.env` :
   ```env
   RECAPTCHA_ENABLED=false
   ```

2. **Laisser les clés vides** : En environnement local, si les clés ne sont pas configurées ou si le token est vide, la validation reCAPTCHA sera automatiquement ignorée.

## Utilisation

reCAPTCHA est automatiquement intégré dans :
- Le formulaire de connexion (`/login`)
- Le formulaire d'onboarding (étape 2)

## Dépannage

Si vous voyez l'erreur "La vérification reCAPTCHA a échoué" :

1. **Vérifiez que les domaines sont bien enregistrés** dans la console reCAPTCHA (c'est la cause la plus fréquente)
2. **Vérifiez les logs Laravel** (`storage/logs/laravel.log`) pour voir les détails de l'erreur
3. **En local, vous pouvez temporairement désactiver** reCAPTCHA avec `RECAPTCHA_ENABLED=false`
4. **Vérifiez que le script reCAPTCHA se charge** correctement dans le navigateur (console du navigateur)
5. **Vérifiez les codes d'erreur** dans les logs :
   - `invalid-input-secret` : La clé secrète est incorrecte
   - `invalid-input-response` : Le token est invalide ou expiré
   - `hostname-mismatch` : Le domaine n'est pas autorisé (ajoutez-le dans la console reCAPTCHA)

## Test en local

Pour tester en local sans clés reCAPTCHA réelles :
- Laissez `RECAPTCHA_ENABLED=false` dans `.env`
- Ou laissez les clés vides : la validation sera automatiquement acceptée en local

Pour tester avec de vraies clés :
1. Créez un site de test sur https://www.google.com/recaptcha/admin
2. Utilisez `localhost` et `127.0.0.1` comme domaines autorisés
3. Ajoutez les clés dans votre `.env`
4. Testez avec `subdomain.localhost:8000`