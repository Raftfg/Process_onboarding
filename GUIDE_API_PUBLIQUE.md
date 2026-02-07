# Guide : Rendre l'API Accessible Publiquement

Ce guide explique comment rendre votre API d'onboarding accessible publiquement pour que d'autres applications puissent l'intégrer.

## 📋 Table des matières

1. [Configuration CORS](#1-configuration-cors)
2. [Déploiement Public](#2-déploiement-public)
3. [Documentation de l'API](#3-documentation-de-lapi)
4. [Authentification](#4-authentification)
5. [Endpoints Disponibles](#5-endpoints-disponibles)
6. [Exemples d'Intégration](#6-exemples-dintégration)
7. [Sécurité](#7-sécurité)

---

## 1. Configuration CORS

### Vérifier la configuration CORS

Votre application utilise déjà le middleware CORS de Laravel. Vérifiez la configuration dans `config/cors.php` :

```php
<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['*'], // En production, spécifiez les domaines autorisés
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
```

### Configuration pour Production

Pour la production, modifiez `allowed_origins` pour autoriser uniquement les domaines de confiance :

```php
'allowed_origins' => [
    'https://votre-domaine.com',
    'https://app-client1.com',
    'https://app-client2.com',
],
```

Ou utilisez des variables d'environnement :

```php
'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', '*')),
```

Dans votre `.env` :

```env
CORS_ALLOWED_ORIGINS=https://app-client1.com,https://app-client2.com
```

---

## 2. Déploiement Public

### A. Configuration du Serveur Web

#### Apache (.htaccess)

Si vous utilisez Apache, assurez-vous que votre `.htaccess` dans `public/` contient :

```apache
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

#### Nginx

Configuration Nginx recommandée :

```nginx
server {
    listen 80;
    server_name api.votre-domaine.com;
    root /var/www/votre-projet/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### B. Variables d'Environnement

Assurez-vous que votre `.env` de production contient :

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.votre-domaine.com

# Base de données
DB_CONNECTION=mysql
DB_HOST=votre-host
DB_PORT=3306
DB_DATABASE=votre-database
DB_USERNAME=votre-user
DB_PASSWORD=votre-password

# Mail (pour envoyer les emails d'activation)
MAIL_MAILER=smtp
MAIL_HOST=smtp.votre-domaine.com
MAIL_PORT=587
MAIL_USERNAME=votre-email
MAIL_PASSWORD=votre-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@votre-domaine.com
MAIL_FROM_NAME="${APP_NAME}"

# CORS (optionnel)
CORS_ALLOWED_ORIGINS=https://app-client1.com,https://app-client2.com
```

### C. SSL/HTTPS

**IMPORTANT** : Utilisez toujours HTTPS en production pour sécuriser les clés API.

1. **Let's Encrypt (Gratuit)** :
```bash
sudo certbot --nginx -d api.votre-domaine.com
```

2. **Cloudflare** : Utilisez le proxy Cloudflare pour SSL automatique

3. **AWS Certificate Manager** : Si vous utilisez AWS

---

## 3. Documentation de l'API

### A. Documentation Existante

Vous avez déjà plusieurs documents de documentation :

- **`API_SPECIFICATION.md`** : Spécification complète de l'API
- **`GUIDE_INTEGRATION_ONBOARDING_STATELESS.md`** : Guide d'intégration détaillé
- **`UTILITE_REPONSES_API.md`** : Utilité de chaque champ de réponse

### B. Créer une Documentation Interactive (Swagger/OpenAPI)

Pour une documentation interactive, vous pouvez utiliser Laravel Swagger :

```bash
composer require darkaonline/l5-swagger
php artisan vendor:publish --provider "L5Swagger\L5SwaggerServiceProvider"
```

Puis ajoutez les annotations dans vos contrôleurs :

```php
/**
 * @OA\Post(
 *     path="/api/v1/onboarding/start",
 *     summary="Démarrer un onboarding",
 *     tags={"Onboarding"},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"email", "organization_name"},
 *             @OA\Property(property="email", type="string", example="admin@example.com"),
 *             @OA\Property(property="organization_name", type="string", example="Clinique du Lac")
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="Onboarding démarré avec succès"
 *     )
 * )
 */
```

Accédez ensuite à : `https://api.votre-domaine.com/api/documentation`

### C. Page de Documentation Publique

Créez une route publique pour la documentation :

```php
// routes/web.php
Route::get('/api-docs', function () {
    return view('api.documentation');
})->name('api.documentation');
```

---

## 4. Authentification

### A. Enregistrement d'une Application

Les applications clientes doivent d'abord s'enregistrer :

**Endpoint** : `POST /api/v1/applications/register`

```bash
curl -X POST https://api.votre-domaine.com/api/v1/applications/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Mon Application",
    "contact_email": "dev@monapp.com",
    "description": "Description de mon application"
  }'
```

**Réponse** :
```json
{
  "success": true,
  "app_id": "app_abc123",
  "master_key": "mk_live_xyz789...",
  "message": "Application enregistrée avec succès"
}
```

⚠️ **IMPORTANT** : La `master_key` n'est affichée qu'une seule fois. Stockez-la en sécurité !

### B. Utilisation de la Master Key

Toutes les requêtes API doivent inclure la master key dans le header :

```bash
curl -X POST https://api.votre-domaine.com/api/v1/onboarding/start \
  -H "Content-Type: application/json" \
  -H "X-Master-Key: mk_live_xyz789..." \
  -d '{
    "email": "admin@example.com",
    "organization_name": "Clinique du Lac"
  }'
```

---

## 5. Endpoints Disponibles

### Endpoints Principaux

| Endpoint | Méthode | Description | Auth |
|----------|---------|-------------|------|
| `/api/v1/applications/register` | POST | Enregistrer une nouvelle application | ❌ |
| `/api/v1/onboarding/start` | POST | Démarrer un onboarding | ✅ Master Key |
| `/api/v1/onboarding/provision` | POST | Provisionner l'infrastructure | ✅ Master Key |
| `/api/v1/onboarding/status/{uuid}` | GET | Obtenir le statut | ✅ Master Key |
| `/api/v1/onboarding/{uuid}/complete` | POST | Marquer comme complété | ✅ Master Key |

### Documentation Complète

Consultez `API_SPECIFICATION.md` pour la documentation complète de tous les endpoints.

---

## 6. Exemples d'Intégration

### A. JavaScript (Node.js / Fetch)

```javascript
const API_BASE_URL = 'https://api.votre-domaine.com/api/v1';
const MASTER_KEY = 'mk_live_xyz789...';

// Démarrer un onboarding
async function startOnboarding(email, organizationName) {
  const response = await fetch(`${API_BASE_URL}/onboarding/start`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Master-Key': MASTER_KEY
    },
    body: JSON.stringify({
      email: email,
      organization_name: organizationName
    })
  });

  return await response.json();
}

// Utilisation
const result = await startOnboarding('admin@example.com', 'Clinique du Lac');
console.log('UUID:', result.uuid);
console.log('Subdomain:', result.subdomain);
```

### B. PHP (Guzzle)

```php
<?php

use GuzzleHttp\Client;

$client = new Client([
    'base_uri' => 'https://api.votre-domaine.com/api/v1',
    'headers' => [
        'X-Master-Key' => 'mk_live_xyz789...',
        'Content-Type' => 'application/json'
    ]
]);

// Démarrer un onboarding
$response = $client->post('/onboarding/start', [
    'json' => [
        'email' => 'admin@example.com',
        'organization_name' => 'Clinique du Lac'
    ]
]);

$data = json_decode($response->getBody(), true);
echo "UUID: " . $data['uuid'] . "\n";
echo "Subdomain: " . $data['subdomain'] . "\n";
```

### C. Python (Requests)

```python
import requests

API_BASE_URL = 'https://api.votre-domaine.com/api/v1'
MASTER_KEY = 'mk_live_xyz789...'

headers = {
    'Content-Type': 'application/json',
    'X-Master-Key': MASTER_KEY
}

# Démarrer un onboarding
response = requests.post(
    f'{API_BASE_URL}/onboarding/start',
    headers=headers,
    json={
        'email': 'admin@example.com',
        'organization_name': 'Clinique du Lac'
    }
)

data = response.json()
print(f"UUID: {data['uuid']}")
print(f"Subdomain: {data['subdomain']}")
```

### D. cURL

```bash
# Démarrer un onboarding
curl -X POST https://api.votre-domaine.com/api/v1/onboarding/start \
  -H "Content-Type: application/json" \
  -H "X-Master-Key: mk_live_xyz789..." \
  -d '{
    "email": "admin@example.com",
    "organization_name": "Clinique du Lac"
  }'
```

---

## 7. Sécurité

### A. Bonnes Pratiques

1. **Toujours utiliser HTTPS** en production
2. **Ne jamais exposer la master key** dans le code client (frontend)
3. **Utiliser des variables d'environnement** pour stocker les clés
4. **Implémenter le rate limiting** (déjà en place)
5. **Valider toutes les entrées** côté serveur
6. **Logger les accès** pour audit

### B. Rate Limiting

Votre API a déjà un rate limiting configuré :

- `/start` : 10 requêtes/minute par application
- `/provision` : 5 requêtes/minute par application
- `/status` : 30 requêtes/minute par application

### C. Monitoring

Configurez un monitoring pour :
- Surveiller les erreurs API
- Détecter les abus
- Suivre les performances

**Outils recommandés** :
- **Sentry** : Pour le suivi des erreurs
- **New Relic** : Pour le monitoring de performance
- **Loggly** : Pour l'analyse des logs

### D. Webhooks

Vous pouvez configurer des webhooks pour être notifié des événements :

```bash
curl -X POST https://api.votre-domaine.com/api/webhooks/register \
  -H "Content-Type: application/json" \
  -H "X-API-Key: votre-api-key" \
  -d '{
    "url": "https://votre-app.com/webhook",
    "events": ["onboarding.completed", "onboarding.provisioned"]
  }'
```

---

## 8. Checklist de Déploiement

- [ ] Configuration CORS mise à jour
- [ ] SSL/HTTPS configuré
- [ ] Variables d'environnement de production configurées
- [ ] Documentation API accessible publiquement
- [ ] Rate limiting activé
- [ ] Monitoring configuré
- [ ] Logs configurés
- [ ] Backup de la base de données configuré
- [ ] Tests d'intégration effectués
- [ ] Documentation partagée avec les clients

---

## 9. Support

Pour toute question ou problème :

1. **Documentation** : Consultez `GUIDE_INTEGRATION_ONBOARDING_STATELESS.md`
2. **Support Email** : support@votre-domaine.com
3. **Dashboard** : `https://api.votre-domaine.com/applications` pour gérer votre application

---

## 10. Exemple de Flux Complet

```javascript
// 1. Enregistrer l'application (une seule fois)
const registerResponse = await fetch('https://api.votre-domaine.com/api/v1/applications/register', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    name: 'Mon Application',
    contact_email: 'dev@monapp.com'
  })
});
const { master_key } = await registerResponse.json();

// 2. Démarrer un onboarding
const startResponse = await fetch('https://api.votre-domaine.com/api/v1/onboarding/start', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-Master-Key': master_key
  },
  body: JSON.stringify({
    email: 'admin@example.com',
    organization_name: 'Clinique du Lac'
  })
});
const { uuid, subdomain } = await startResponse.json();

// 3. Provisionner l'infrastructure
const provisionResponse = await fetch('https://api.votre-domaine.com/api/v1/onboarding/provision', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-Master-Key': master_key
  },
  body: JSON.stringify({ uuid })
});
const provisionData = await provisionResponse.json();

// 4. Vérifier le statut
const statusResponse = await fetch(`https://api.votre-domaine.com/api/v1/onboarding/status/${uuid}`, {
  headers: { 'X-Master-Key': master_key }
});
const status = await statusResponse.json();

// 5. Marquer comme complété
await fetch(`https://api.votre-domaine.com/api/v1/onboarding/${uuid}/complete`, {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-Master-Key': master_key
  },
  body: JSON.stringify({
    tenant_id: 'votre-tenant-id',
    metadata: { /* métadonnées optionnelles */ }
  })
});
```

---

**Félicitations !** Votre API est maintenant prête à être utilisée publiquement. 🚀
