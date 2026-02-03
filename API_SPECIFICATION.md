# Spécification API - Akasi Onboarding

Ce document détaille l'utilisation de l'API REST et l'intégration des webhooks pour le microservice d'onboarding d'Akasi Group.

## 🔑 Authentification

Toutes les requêtes API (sauf spécifié autrement) doivent inclure les headers suivants :

| Header | Valeur | Requis |
| :--- | :--- | :--- |
| `X-API-Key` | Votre clé secrète | Oui (toutes) |
| `X-App-Name` | Nom de l'application source | Oui (onboarding externe) |
| `Authorization` | `Bearer <votre_cle_api>` | Alternative à `X-API-Key` |

Les clés API sont générées et gérées depuis le **Dashboard Super Admin** (`/admin/api-keys`).

---

## 📡 Endpoints REST

### 1. Créer un Onboarding
Démarre le processus de création d'un nouveau tenant.

- **URL** : `/api/v1/onboarding/create`
- **Méthode** : `POST`
- **Corps de la requête** :

```json
{
  "organization": {
    "name": "Nom de l'Hôpital",
    "address": "123 Rue de la Santé, Libreville",
    "phone": "+241 01 23 45 67",
    "email": "contact@hopital-libreville.com"
  },
  "admin": {
    "first_name": "Alice",
    "last_name": "Durand",
    "email": "admin@hopital-libreville.com"
  },
  "metadata": {
    "external_id": "CRM-789",
    "plan": "premium"
  },
  "options": {
    "send_welcome_email": true,
    "auto_login": false
  }
}
```

- **Réponse (Succès 201)** :

```json
{
  "success": true,
  "data": {
    "subdomain": "hopital-libreville",
    "database_name": "tenant_hopital_libreville",
    "url": "http://hopital-libreville.votre-domaine.com",
    "admin_email": "admin@hopital-libreville.com",
    "created_at": "2026-02-02T12:00:00Z"
  }
}
```

### 2. Statut de l'Onboarding
Vérifie l'état d'avancement d'un tenant.

- **URL** : `/api/onboarding/status/{subdomain}`
- **Méthode** : `GET`
- **Réponse** :

```json
{
  "success": true,
  "data": {
    "subdomain": "hopital-libreville",
    "status": "completed",
    "database_name": "tenant_hopital_libreville",
    "created_at": "2026-02-02T11:00:00Z"
  }
}
```
*Statuts possibles : `pending`, `processing`, `pending_activation`, `completed`, `failed`.*

### 3. Onboarding Externe (Intégration Secteur)
Endpoint spécialisé pour l'onboarding depuis une application tierce (ex: SIH, logiciel externe). Ce point de terminaison permet de passer des scripts SQL de migration personnalisés pour préparer l'environnement du nouveau tenant.

- **URL** : `/api/v1/onboarding/external`
- **Méthode** : `POST`
- **Headers Requis** : 
  - `X-API-Key` : Votre clé API.
  - `X-App-Name` : Identifiant unique de votre application (ex: `Secteur-Sante-v1`).
- **Corps de la requête** :

> [!IMPORTANT]
> **Isolation par Application** : Le nom de l'organisation est unique **par application**. Une organisation nommée "Clinique A" peut exister pour l'application "App-1" et pour "App-2" sans conflit. En revanche, "App-1" ne peut pas créer deux fois la même organisation.

```json
{
  "email": "admin@secteur-sante.com",
  "organization_name": "Clinique Du Lac",
  "callback_url": "https://secteur-sante.com/api/tenants/confirm",
  "metadata": {
    "external_id": "SIH-123456"
  },
  "migrations": [
    {
      "filename": "2026_02_03_create_patients_table.php",
      "content": "<?php\nuse Illuminate\\Database\\Migrations\\Migration;\nuse Illuminate\\Database\\Schema\\Blueprint;\nuse Illuminate\\Support\Facades\\Schema;\n\nreturn new class extends Migration {\n    public function up() {\n        Schema::create('specific_patients', function (Blueprint $table) {\n            $table->id();\n            $table->string('name');\n            $table->timestamps();\n        });\n    }\n};"
    }
  ]
}
```

- **Réponse (Succès 200)** :
```json
{
  "success": true,
  "message": "Onboarding externe initié avec succès",
  "result": {
    "subdomain": "clinique-du-lac",
    "activation_token": "...",
    "url": "http://clinique-du-lac.localhost:8000"
  }
}
```

#### Callback de Confirmation
Si un `callback_url` est fourni, le microservice envoie une requête `POST` à cette URL une fois que le tenant est provisionné.

**Payload du Callback** :
```json
{
  "success": true,
  "subdomain": "clinique-du-lac",
  "database": "tenant_clinique_du_lac",
  "url": "http://clinique-du-lac.localhost:8000",
  "email": "admin@secteur-sante.com",
  "organization_name": "Clinique Du Lac",
  "activation_token": "...",
  "metadata": {
    "external_id": "SIH-123456"
  }
}
```

---

## 🪝 Système de Webhooks

Le microservice peut notifier votre application lors d'événements importants.

### Événements Supportés
- `onboarding.completed` : Déclenché quand le tenant est prêt et activé.
- `onboarding.failed` : Déclenché en cas d'erreur lors du provisioning.
- `test` : Utilisé pour valider votre URL de réception.

### Enregistrement d'un Webhook
- **URL** : `/api/webhooks/register`
- **Méthode** : `POST`
- **Corps** :
```json
{
  "url": "https://votre-app.com/api/webhooks/akasi",
  "events": ["onboarding.completed", "onboarding.failed"]
}
```
*La réponse contiendra un `secret` à conserver pour la vérification des signatures.*

### Vérification de la Signature (Sécurité)
Chaque requête de webhook contient un header `X-Akasi-Signature`. Vous **devez** vérifier cette signature pour vous assurer que l'appel provient bien de notre service.

**Algorithme (PHP)** :
```php
$payload = file_get_contents('php://input');
$signatureReceived = $_SERVER['HTTP_X_AKASI_SIGNATURE'];
$secret = 'votre_webhook_secret';

$signatureExpected = hash_hmac('sha256', $payload, $secret);

if (hash_equals($signatureExpected, $signatureReceived)) {
    // Signature valide
}
```

---

## 🏗 Architecture Technique

### Isolation des Données
Ce microservice utilise une stratégie **Database-per-Tenant**. 
1. La base de données `mysql` (principale) stocke les sessions d'onboarding et les configurations globales.
2. Chaque tenant possède sa propre base de données `tenant_xxxxx`.
3. Le système bascule dynamiquement la connexion Laravel via le `TenantService` lors de l'accès par sous-domaine.

---
© 2026 Akasi Group - Documentation Technique
