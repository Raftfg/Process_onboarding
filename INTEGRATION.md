# Guide d'Intégration - Microservice d'Onboarding Multi-Tenant

Ce guide explique comment intégrer le microservice d'onboarding MedKey dans votre projet, que ce soit une application Laravel, React, Vue, ou toute autre technologie.

## 📋 Table des matières

1. [Vue d'ensemble](#vue-densemble)
2. [Installation](#installation)
3. [API REST](#api-rest)
4. [Exemples d'intégration](#exemples-dintégration)
5. [Webhooks](#webhooks)
6. [Configuration](#configuration)
7. [Dépannage](#dépannage)

## 🎯 Vue d'ensemble

Le microservice d'onboarding permet de :
- Créer automatiquement des bases de données isolées pour chaque tenant
- Générer des sous-domaines uniques
- Créer des utilisateurs administrateurs
- Envoyer des emails de bienvenue
- Gérer l'authentification multi-tenant

### Architecture

```
Votre Application (Frontend/Backend)
         ↓
    API REST / Webhooks
         ↓
Microservice Onboarding
         ↓
    Base de données principale (métadonnées)
         ↓
    Bases de données par tenant (isolées)
```

## 🚀 Installation

### Option 1 : Intégration via API REST (Recommandé)

Aucune installation nécessaire ! Utilisez simplement les endpoints API REST.

### Option 2 : Installation locale

```bash
# Cloner le repository
git clone https://github.com/votre-org/medkey-onboarding.git
cd medkey-onboarding

# Installer les dépendances
composer install

# Configurer l'environnement
cp .env.example .env
php artisan key:generate

# Configurer la base de données
# Éditer .env avec vos paramètres MySQL

# Exécuter les migrations
php artisan migrate

# Démarrer le serveur
php artisan serve
```

## 🔌 API REST

### Base URL

```
Production: https://onboarding.medkey.com/api
Développement: http://localhost:8000/api
```

### Authentification

Pour les intégrations externes, utilisez une clé API :

```http
Authorization: Bearer YOUR_API_KEY
```

**Générer une clé API :**

```bash
php artisan api:generate-key "Nom de votre application"
```

**Lister les clés API :**

```bash
php artisan api:list-keys
```

**Ou via variable d'environnement :**

Ajoutez dans votre `.env` :
```env
API_KEY=your_secret_api_key_here
```

### Endpoints disponibles

#### 1. Créer un onboarding (POST)

**Endpoint:** `POST /api/onboarding/create`

**Headers:**
```http
Content-Type: application/json
Authorization: Bearer YOUR_API_KEY
```

**Body:**
```json
{
  "hospital": {
    "name": "Hôpital Central",
    "address": "123 Rue de la Santé, Paris",
    "phone": "+33 1 23 45 67 89",
    "email": "contact@hopital-central.fr"
  },
  "admin": {
    "first_name": "Jean",
    "last_name": "Dupont",
    "email": "admin@hopital-central.fr",
    "password": "SecurePassword123!"
  },
  "options": {
    "send_welcome_email": true,
    "auto_login": true
  }
}
```

**Réponse (200 OK):**
```json
{
  "success": true,
  "data": {
    "subdomain": "hopital-central-1234567890",
    "database_name": "medkey_hopital-central-1234567890",
    "url": "https://hopital-central-1234567890.medkey.com",
    "admin_email": "admin@hopital-central.fr",
    "created_at": "2024-01-15T10:30:00Z"
  }
}
```

**Réponse d'erreur (400/500):**
```json
{
  "success": false,
  "message": "Erreur de validation",
  "errors": {
    "hospital.name": ["Le nom de l'hôpital est requis"]
  }
}
```

#### 2. Vérifier le statut d'un onboarding (GET)

**Endpoint:** `GET /api/onboarding/status/{subdomain}`

**Headers:**
```http
Authorization: Bearer YOUR_API_KEY
```

**Réponse (200 OK):**
```json
{
  "success": true,
  "data": {
    "subdomain": "hopital-central-1234567890",
    "status": "completed",
    "database_name": "medkey_hopital-central-1234567890",
    "created_at": "2024-01-15T10:30:00Z"
  }
}
```

#### 3. Obtenir les informations d'un tenant (GET)

**Endpoint:** `GET /api/tenant/{subdomain}`

**Headers:**
```http
Authorization: Bearer YOUR_API_KEY
```

**Réponse (200 OK):**
```json
{
  "success": true,
  "data": {
    "subdomain": "hopital-central-1234567890",
    "hospital_name": "Hôpital Central",
    "hospital_address": "123 Rue de la Santé, Paris",
    "hospital_phone": "+33 1 23 45 67 89",
    "hospital_email": "contact@hopital-central.fr",
    "admin_email": "admin@hopital-central.fr",
    "database_name": "medkey_hopital-central-1234567890",
    "status": "completed",
    "created_at": "2024-01-15T10:30:00Z"
  }
}
```

## 💻 Exemples d'intégration

### JavaScript (Fetch API)

```javascript
async function createOnboarding(hospitalData, adminData) {
  try {
    const response = await fetch('https://onboarding.medkey.com/api/onboarding/create', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer YOUR_API_KEY'
      },
      body: JSON.stringify({
        hospital: hospitalData,
        admin: adminData,
        options: {
          send_welcome_email: true,
          auto_login: true
        }
      })
    });

    const result = await response.json();
    
    if (result.success) {
      console.log('Onboarding créé:', result.data);
      // Rediriger vers le dashboard du tenant
      window.location.href = result.data.url;
    } else {
      console.error('Erreur:', result.message);
    }
  } catch (error) {
    console.error('Erreur réseau:', error);
  }
}

// Utilisation
createOnboarding(
  {
    name: "Hôpital Central",
    address: "123 Rue de la Santé",
    phone: "+33 1 23 45 67 89",
    email: "contact@hopital-central.fr"
  },
  {
    first_name: "Jean",
    last_name: "Dupont",
    email: "admin@hopital-central.fr",
    password: "SecurePassword123!"
  }
);
```

### PHP (Guzzle)

```php
<?php

use GuzzleHttp\Client;

function createOnboarding($hospitalData, $adminData) {
    $client = new Client([
        'base_uri' => 'https://onboarding.medkey.com/api',
        'headers' => [
            'Authorization' => 'Bearer YOUR_API_KEY',
            'Content-Type' => 'application/json',
        ]
    ]);

    try {
        $response = $client->post('/onboarding/create', [
            'json' => [
                'hospital' => $hospitalData,
                'admin' => $adminData,
                'options' => [
                    'send_welcome_email' => true,
                    'auto_login' => true
                ]
            ]
        ]);

        $result = json_decode($response->getBody(), true);
        
        if ($result['success']) {
            return $result['data'];
        } else {
            throw new Exception($result['message']);
        }
    } catch (\Exception $e) {
        error_log('Erreur onboarding: ' . $e->getMessage());
        throw $e;
    }
}

// Utilisation
$result = createOnboarding(
    [
        'name' => 'Hôpital Central',
        'address' => '123 Rue de la Santé',
        'phone' => '+33 1 23 45 67 89',
        'email' => 'contact@hopital-central.fr'
    ],
    [
        'first_name' => 'Jean',
        'last_name' => 'Dupont',
        'email' => 'admin@hopital-central.fr',
        'password' => 'SecurePassword123!'
    ]
);

echo "Subdomain créé: " . $result['subdomain'];
```

### cURL

```bash
curl -X POST https://onboarding.medkey.com/api/onboarding/create \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -d '{
    "hospital": {
      "name": "Hôpital Central",
      "address": "123 Rue de la Santé, Paris",
      "phone": "+33 1 23 45 67 89",
      "email": "contact@hopital-central.fr"
    },
    "admin": {
      "first_name": "Jean",
      "last_name": "Dupont",
      "email": "admin@hopital-central.fr",
      "password": "SecurePassword123!"
    },
    "options": {
      "send_welcome_email": true,
      "auto_login": true
    }
  }'
```

### React (avec Axios)

```jsx
import axios from 'axios';
import { useState } from 'react';

function OnboardingForm() {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const handleSubmit = async (formData) => {
    setLoading(true);
    setError(null);

    try {
      const response = await axios.post(
        'https://onboarding.medkey.com/api/onboarding/create',
        {
          hospital: {
            name: formData.hospitalName,
            address: formData.hospitalAddress,
            phone: formData.hospitalPhone,
            email: formData.hospitalEmail
          },
          admin: {
            first_name: formData.adminFirstName,
            last_name: formData.adminLastName,
            email: formData.adminEmail,
            password: formData.adminPassword
          },
          options: {
            send_welcome_email: true,
            auto_login: true
          }
        },
        {
          headers: {
            'Authorization': 'Bearer YOUR_API_KEY',
            'Content-Type': 'application/json'
          }
        }
      );

      if (response.data.success) {
        // Rediriger vers le dashboard du tenant
        window.location.href = response.data.data.url;
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Une erreur est survenue');
    } finally {
      setLoading(false);
    }
  };

  return (
    <form onSubmit={handleSubmit}>
      {/* Vos champs de formulaire */}
      <button type="submit" disabled={loading}>
        {loading ? 'Création en cours...' : 'Créer mon compte'}
      </button>
      {error && <div className="error">{error}</div>}
    </form>
  );
}
```

### Vue.js (avec Axios)

```vue
<template>
  <form @submit.prevent="createOnboarding">
    <!-- Vos champs de formulaire -->
    <button type="submit" :disabled="loading">
      {{ loading ? 'Création en cours...' : 'Créer mon compte' }}
    </button>
    <div v-if="error" class="error">{{ error }}</div>
  </form>
</template>

<script>
import axios from 'axios';

export default {
  data() {
    return {
      loading: false,
      error: null,
      formData: {
        hospital: {
          name: '',
          address: '',
          phone: '',
          email: ''
        },
        admin: {
          first_name: '',
          last_name: '',
          email: '',
          password: ''
        }
      }
    };
  },
  methods: {
    async createOnboarding() {
      this.loading = true;
      this.error = null;

      try {
        const response = await axios.post(
          'https://onboarding.medkey.com/api/onboarding/create',
          {
            hospital: this.formData.hospital,
            admin: this.formData.admin,
            options: {
              send_welcome_email: true,
              auto_login: true
            }
          },
          {
            headers: {
              'Authorization': 'Bearer YOUR_API_KEY',
              'Content-Type': 'application/json'
            }
          }
        );

        if (response.data.success) {
          window.location.href = response.data.data.url;
        }
      } catch (err) {
        this.error = err.response?.data?.message || 'Une erreur est survenue';
      } finally {
        this.loading = false;
      }
    }
  }
};
</script>
```

## 🔔 Webhooks

Vous pouvez configurer des webhooks pour être notifié des événements d'onboarding.

### Enregistrer un webhook

```http
POST /api/webhooks/register
Authorization: Bearer YOUR_API_KEY
Content-Type: application/json

{
  "url": "https://votre-app.com/webhooks/onboarding",
  "events": ["onboarding.completed", "onboarding.failed"],
  "timeout": 30
}
```

**Réponse (201 Created):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "url": "https://votre-app.com/webhooks/onboarding",
    "events": ["onboarding.completed", "onboarding.failed"],
    "secret": "abc123...",
    "created_at": "2024-01-15T10:30:00Z"
  }
}
```

⚠️ **Important:** Sauvegardez le `secret` pour vérifier la signature des webhooks reçus.

### Lister les webhooks

```http
GET /api/webhooks
Authorization: Bearer YOUR_API_KEY
```

### Désactiver un webhook

```http
DELETE /api/webhooks/{id}
Authorization: Bearer YOUR_API_KEY
```

### Événements disponibles

- `onboarding.completed` : Déclenché quand un onboarding est terminé avec succès
- `onboarding.failed` : Déclenché quand un onboarding échoue

### Format du webhook

**Headers reçus :**
```http
X-Webhook-Signature: sha256=abc123...
X-Webhook-Event: onboarding.completed
Content-Type: application/json
```

**Body :**
```json
{
  "event": "onboarding.completed",
  "timestamp": "2024-01-15T10:30:00Z",
  "data": {
    "subdomain": "hopital-central-1234567890",
    "database_name": "medkey_hopital-central-1234567890",
    "hospital_name": "Hôpital Central",
    "admin_email": "admin@hopital-central.fr",
    "url": "https://hopital-central-1234567890.medkey.com"
  }
}
```

### Vérifier la signature

Pour sécuriser vos webhooks, vérifiez la signature :

```php
// PHP
$signature = hash_hmac('sha256', $request->getContent(), $webhookSecret);
$receivedSignature = $request->header('X-Webhook-Signature');

if (!hash_equals($signature, $receivedSignature)) {
    // Signature invalide, rejeter la requête
    abort(401);
}
```

```javascript
// Node.js
const crypto = require('crypto');

function verifyWebhook(payload, signature, secret) {
  const expectedSignature = crypto
    .createHmac('sha256', secret)
    .update(JSON.stringify(payload))
    .digest('hex');
  
  return crypto.timingSafeEqual(
    Buffer.from(signature),
    Buffer.from(expectedSignature)
  );
}
```

## ⚙️ Configuration

### Variables d'environnement requises

```env
# Base de données principale
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=onboarding
DB_USERNAME=root
DB_PASSWORD=your_password

# Credentials root MySQL (pour créer les bases de données)
DB_ROOT_USERNAME=root
DB_ROOT_PASSWORD=your_root_password

# Domaine de base pour les sous-domaines
SUBDOMAIN_BASE_DOMAIN=medkey.com

# Configuration email
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_FROM_ADDRESS=noreply@medkey.com
MAIL_FROM_NAME=MedKey

# Clé API (pour les intégrations externes)
API_KEY=your_secret_api_key
```

## 🐛 Dépannage

### Erreur 401 (Unauthorized)

Vérifiez que votre clé API est correcte et incluse dans les headers :
```http
Authorization: Bearer YOUR_API_KEY
```

### Erreur 400 (Bad Request)

Vérifiez que tous les champs requis sont présents et valides :
- `hospital.name` (requis)
- `admin.first_name` (requis)
- `admin.last_name` (requis)
- `admin.email` (requis, format email valide)
- `admin.password` (requis, minimum 8 caractères)

### Erreur 500 (Internal Server Error)

Contactez le support avec les détails de l'erreur. Vérifiez les logs du serveur.

### Base de données non créée

Vérifiez que :
- Les credentials MySQL root sont corrects
- L'utilisateur MySQL a les droits de création de bases de données
- Le serveur MySQL est accessible

## 📚 Documentation Swagger/OpenAPI

Une documentation OpenAPI complète est disponible dans le fichier `openapi.yaml`.

Pour visualiser la documentation :
1. Importez `openapi.yaml` dans [Swagger Editor](https://editor.swagger.io/)
2. Ou utilisez [Postman](https://www.postman.com/) pour importer la collection

## 🔑 Gestion des clés API

### Générer une clé API

```bash
php artisan api:generate-key "Mon Application"
```

Options disponibles :
- `--expires=2024-12-31 23:59:59` : Date d'expiration
- `--limit=100` : Limite de requêtes par minute

### Lister les clés API

```bash
php artisan api:list-keys
```

### Désactiver une clé API

```php
use App\Models\ApiKey;

$apiKey = ApiKey::find($id);
$apiKey->update(['is_active' => false]);
```

## 📞 Support

Pour toute question ou problème :
- Email: support@medkey.com
- Documentation: https://docs.medkey.com/onboarding
- Issues GitHub: https://github.com/votre-org/medkey-onboarding/issues

## 📄 Licence

MIT
