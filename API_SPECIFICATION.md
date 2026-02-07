# Spécification API - Microservice Onboarding

Ce document détaille l'utilisation de l'API REST pour le microservice d'infrastructure et d'enregistrement.

> **Note** : Le microservice ne crée plus les tenants. Il fournit uniquement l'infrastructure (bases de données, sous-domaines, DNS/SSL) et enregistre les métadonnées d'onboarding.

## 🔑 Authentification

Toutes les requêtes API (sauf spécifié autrement) doivent inclure les headers suivants :

| Header | Valeur | Requis |
| :--- | :--- | :--- |
| `X-API-Key` | Votre clé secrète | **OUI** (toutes) |
| `X-App-Name` | Nom de l'application (ex: `Ejustice`) | **OUI** (Toutes requêtes protégées) |
| `Authorization` | `Bearer <votre_cle_api>` | Déprécié (préférez `X-API-Key`) |

Les clés API peuvent être :
- Générées via self-service par les applications (`POST /api/v1/applications/{app_id}/api-keys`)
- Générées automatiquement lors de l'onboarding (si `generate_api_key: true`)
- Gérées depuis le **Dashboard Super Admin** (`/admin/api-keys`)

---

## 📡 Endpoints REST (Onboarding stateless)

### 1. Démarrer un Onboarding

**But** : enregistrer email + organisation + sous-domaine dans la base centrale.

- **URL** : `/api/v1/onboarding/start`
- **Méthode** : `POST`
- **Headers Requis** :
  - `X-Master-Key` : master key de votre application (obtenue lors de l'enregistrement).
- **Corps de la requête** :

```json
{
  "email": "admin@example.com",
  "organization_name": "Clinique du Lac"
}
```

- **Réponse (Succès 201)** :

```json
{
  "success": true,
  "uuid": "550e8400-e29b-41d4-a716-446655440000",
  "subdomain": "clinique-du-lac",
  "email": "admin@example.com",
  "organization_name": "Clinique du Lac",
  "onboarding_status": "pending",
  "metadata": {
    "created_at": "2026-02-07T10:30:00Z",
    "updated_at": "2026-02-07T10:30:00Z",
    "dns_configured": false,
    "ssl_configured": false,
    "infrastructure_status": "pending",
    "api_key_generated": false,
    "provisioning_attempts": 0
  }
}
```

> `onboarding_status` correspond à l'état de l'enregistrement central (`pending`, puis `activated` ou `cancelled`).
> 
> **Metadata enrichies** : Les réponses incluent désormais des métadonnées techniques pour le monitoring :
> - `created_at`, `updated_at` : timestamps ISO 8601
> - `dns_configured`, `ssl_configured` : état de l'infrastructure
> - `infrastructure_status` : `pending` | `partial` | `ready`
> - `api_key_generated` : indique si une clé API a été générée
> - `provisioning_attempts` : nombre de tentatives de provisioning

---

### 2. Provisionner l'Infrastructure

**But** : configurer DNS/SSL et générer éventuellement une clé API.

- **URL** : `/api/v1/onboarding/provision`
- **Méthode** : `POST`
- **Headers Requis** :
  - `X-Master-Key` : master key de votre application.
- **Corps de la requête** :

```json
{
  "uuid": "550e8400-e29b-41d4-a716-446655440000",
  "generate_api_key": true
}
```

- **Réponse (Succès 200)** :

```json
{
  "success": true,
  "uuid": "550e8400-e29b-41d4-a716-446655440000",
  "subdomain": "clinique-du-lac",
  "email": "admin@example.com",
  "organization_name": "Clinique du Lac",
  "onboarding_status": "activated",
  "api_key": "ak_abc123...",       // transmis une seule fois si généré
  "api_secret": "ak_abc123...",     // même valeur, à stocker côté client
  "metadata": {
    "created_at": "2026-02-07T10:30:00Z",
    "updated_at": "2026-02-07T10:35:00Z",
    "dns_configured": true,
    "ssl_configured": true,
    "infrastructure_status": "ready",
    "api_key_generated": true,
    "provisioning_attempts": 1,
    "is_idempotent": false
  }
}
```

> Si l'onboarding est déjà provisionné, l'appel est **idempotent** : 
> - `api_key` et `api_secret` seront `null`
> - `onboarding_status` restera inchangé
> - `metadata.is_idempotent` sera `true`

---

### 3. Consulter le Statut d'un Onboarding

- **URL** : `/api/v1/onboarding/status/{uuid}`
- **Méthode** : `GET`
- **Headers Requis** :
  - `X-Master-Key` : master key de votre application.

- **Réponse (Succès 200)** :

```json
{
  "success": true,
  "uuid": "550e8400-e29b-41d4-a716-446655440000",
  "subdomain": "clinique-du-lac",
  "email": "admin@example.com",
  "organization_name": "Clinique du Lac",
  "onboarding_status": "activated",
  "dns_configured": true,
  "ssl_configured": true,
  "metadata": {
    "created_at": "2026-02-07T10:30:00Z",
    "updated_at": "2026-02-07T10:35:00Z",
    "dns_configured": true,
    "ssl_configured": true,
    "infrastructure_status": "ready",
    "api_key_generated": true,
    "provisioning_attempts": 1
  }
}
```

---

## 🚦 Rate Limiting

Le microservice applique des limites de taux pour protéger l'infrastructure et garantir une utilisation équitable.

### Limites par Endpoint

| Endpoint | Limite | Période | Clé de limitation |
|----------|--------|---------|-------------------|
| `POST /api/v1/onboarding/start` | 10 requêtes | 1 heure | Par application (X-Master-Key) |
| `POST /api/v1/onboarding/provision` | 1 requête | 24 heures | Par UUID (tenant) |
| `GET /api/v1/onboarding/status/{uuid}` | 100 requêtes | 1 heure | Par application (X-Master-Key) |

### Limite Globale par IP

- **50 requêtes / heure** pour tous les endpoints confondus (par adresse IP)

### Réponse en cas de dépassement (429)

```json
{
  "success": false,
  "message": "Trop de requêtes. Veuillez réessayer plus tard.",
  "error": "rate_limit_exceeded",
  "retry_after_minutes": 15
}
```

**Headers de réponse** :
- `X-RateLimit-Limit` : limite maximale
- `X-RateLimit-Remaining` : nombre de requêtes restantes
- `X-RateLimit-Reset` : timestamp de réinitialisation
- `Retry-After` : nombre de secondes avant de pouvoir réessayer

### Bonnes pratiques

- Implémentez un **backoff exponentiel** en cas de réponse 429
- Surveillez les headers `X-RateLimit-Remaining` pour éviter les dépassements
- Utilisez `/status` plutôt que `/provision` pour vérifier l'état (limite plus élevée)

---

## 📋 Codes HTTP et Gestion d'Erreurs

### Codes de Succès

| Code | Description | Endpoint |
|------|-------------|----------|
| `200` | Succès (provisioning, status) | `POST /provision`, `GET /status` |
| `201` | Créé avec succès | `POST /start` |

### Codes d'Erreur Client

| Code | Description | Exemple |
|------|-------------|---------|
| `400` | Requête invalide | Paramètres manquants |
| `401` | Non autorisé | Master key invalide ou absente |
| `403` | Interdit | Application suspendue |
| `404` | Non trouvé | UUID introuvable pour cette application |
| `422` | Erreur de validation | Email invalide, sous-domaine déjà utilisé |
| `429` | Trop de requêtes | Rate limit dépassé |

### Codes d'Erreur Serveur

| Code | Description |
|------|-------------|
| `500` | Erreur interne du serveur |
| `503` | Service temporairement indisponible |

### Format des Erreurs

Toutes les erreurs suivent ce format :

```json
{
  "success": false,
  "message": "Description de l'erreur",
  "error": "code_erreur",
  "errors": {
    "field": ["Message de validation"]
  }
}
```

### Bonnes pratiques de logging côté client

Lors de l'intégration, loggez systématiquement :

```php
// Exemple PHP
$response = $httpClient->post('/api/v1/onboarding/start', [
    'headers' => ['X-Master-Key' => $masterKey],
    'json' => ['email' => $email, 'organization_name' => $orgName]
]);

// Logger pour le debugging et le monitoring
Log::info('Onboarding start request', [
    'uuid' => $response->json()['uuid'] ?? null,
    'status_code' => $response->status(),
    'url' => '/api/v1/onboarding/start',
    'response_body' => $this->sanitizeResponse($response->json()), // Ne pas logger les secrets
]);

// Fonction de sanitization
private function sanitizeResponse(array $data): array
{
    unset($data['api_key'], $data['api_secret'], $data['master_key']);
    return $data;
}
```

**À logger** :
- `uuid` : identifiant unique pour corrélation
- `status_code` : code HTTP de la réponse
- `url` : endpoint appelé
- `response_body` : réponse sanitizée (sans secrets)

**À ne PAS logger** :
- `api_key`, `api_secret`, `master_key` : secrets sensibles

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
