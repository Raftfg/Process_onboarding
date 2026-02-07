# Guide d’intégration – Onboarding Stateless

## 1. Rôle du microservice

**Ce que fait le microservice :**

- Gère une **base centrale d’onboarding** (pas vos données métier).
- Enregistre les informations initiales d’un futur tenant :
  - `email` administrateur,
  - `organization_name` (optionnel, généré si absent),
  - `uuid` unique,
  - `subdomain` généré automatiquement.
- Provisionne l’**infrastructure technique** autour du sous-domaine :
  - DNS (hébergeur),
  - certificat SSL (ou préparation pour SSL).
- Peut générer **optionnellement** une `api_key` / `api_secret`.
- Expose l’**état** de l’onboarding via une API REST stateless.

**Ce qu’il ne fait PAS :**

- Ne crée **PAS** le tenant métier dans votre application.
- Ne crée **PAS** la base de données du tenant (ni tables métier).
- N’envoie **PAS** d’e-mails métier (activation, bienvenue, etc.).
- Ne gère **PAS** l’authentification utilisateur finale.
- Ne maintient **AUCUNE** session utilisateur (pas de cookies, pas de `Session::put()` côté API).

**Sa place dans votre architecture SaaS :**

- Il agit comme un **orchestrateur d’onboarding technique** :
  - centralise l’enregistrement initial,
  - génère le sous-domaine,
  - prépare l’infrastructure,
  - retourne les informations nécessaires pour que **votre application** :
    - crée le tenant,
    - crée la/les base(s) de données,
    - envoie les e-mails,
    - gère l’activation.

---

## 2. Schéma logique d’intégration (texte)

**Composants :**

- **Application cliente** (votre app)  
  - Exemples : SIH, ERP, plateforme SaaS métier.
- **Microservice d’onboarding**  
  - Service externe, exposé en HTTP.
- **Base de données cliente**  
  - Vos propres bases (par tenant, par app, etc.).
- **Flux API**  
  - HTTP/JSON entre votre app et le microservice.

**Flux logique :**

1. **Votre app** appelle le microservice pour **démarrer** un onboarding avec :
   - `email` de l’admin,
   - éventuellement `organization_name`.
2. Le **microservice** :
   - génère un `uuid` unique,
   - génère un `subdomain` (par ex. `clinique-du-lac`),
   - enregistre dans sa **base centrale**,
   - retourne au client : `uuid`, `subdomain`, `onboarding_status=pending`.
3. Votre app décide **quand** lancer le **provisioning** :
   - appel à `/onboarding/provision` avec le `uuid`.
4. Le **microservice** :
   - configure DNS + SSL (ou prépare ces étapes),
   - peut générer une `api_key` + `api_secret` (optionnel),
   - met à jour `onboarding_status` (ex. `activated`),
   - renvoie `subdomain`, `onboarding_status`, et éventuellement la clé API.
5. **Votre app** :
   - crée la base de données du tenant (avec un préfixe dérivé du `subdomain` si vous le souhaitez),
   - crée le tenant métier et les utilisateurs,
   - envoie les e-mails d’activation,
   - gère la suite du workflow (activation, connexion, droits, etc.).
6. À tout moment, votre app peut interroger **le statut** via `/onboarding/status/{uuid}`.

---

## 3. Parcours d’intégration pas à pas

### 3.1. Prérequis

- Avoir **enregistré votre application** dans le microservice (`POST /api/v1/applications/register`).  
  - Vous obtenez :
    - `app_id`
    - `app_name`
    - `master_key` (via `X-Master-Key`) – **à garder secret**.
  - **Note importante** : 
    - L'enregistrement d'application ne crée **pas** de base de données. 
    - Seule la **master key** est nécessaire pour démarrer un onboarding.
    - Aucune base de données n'est requise pour l'application cliente lors de l'enregistrement.
- Savoir consommer une API REST JSON (HTTP client, n'importe quel langage).
- Avoir une stratégie claire pour :
  - créer les **bases de données** de tenants (côté application cliente),
  - nommer ces bases (par ex. préfixer par le `subdomain`),
  - gérer les **e-mails** et l'activation.

### 3.2. Démarrage de l’onboarding

1. Votre app expose un formulaire où l’admin renseigne :
   - son `email`,
   - éventuellement le `nom de l’organisation`.
2. Votre backend appelle :

```http
POST /api/v1/onboarding/start
X-Master-Key: {votre_master_key}
Content-Type: application/json
```

```json
{
  "email": "admin@example.com",
  "organization_name": "Clinique du Lac"
}
```

3. Réponse typique (enrichie avec metadata) :

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

> **💡 Comment les metadata sont gérées :**
> 
> Les metadata sont **construites dynamiquement** dans le contrôleur (`OnboardingController`) à partir des colonnes de la table `onboarding_registrations`. Elles ne sont **pas stockées en JSON** pour garantir la cohérence et éviter la duplication.
> 
> **Source des données :**
> 
> | Champ metadata | Source dans la DB | Mise à jour |
> |----------------|-------------------|-------------|
> | `created_at` | Colonne `created_at` (timestamp Laravel) | Automatique lors de la création |
> | `updated_at` | Colonne `updated_at` (timestamp Laravel) | Automatique à chaque `save()` |
> | `dns_configured` | Colonne `dns_configured` (boolean) | Mis à jour dans `OnboardingOrchestratorService::provision()` |
> | `ssl_configured` | Colonne `ssl_configured` (boolean) | Mis à jour dans `OnboardingOrchestratorService::provision()` |
> | `infrastructure_status` | **Calculé** via `getInfrastructureStatus()` | Calculé à la volée : `"pending"` (DNS+SSL=false), `"partial"` (un seul true), `"ready"` (DNS+SSL=true) |
> | `api_key_generated` | **Dérivé** de `!empty($registration->api_key)` | Dérivé de la colonne `api_key` |
> | `provisioning_attempts` | Colonne `provisioning_attempts` (integer) | Incrémenté dans `OnboardingOrchestratorService::provision()` (sauf si idempotent) |
> 
> **Flux de mise à jour :**
> 1. Lors de `/start` : Les metadata reflètent l'état initial (`pending`, `dns_configured=false`, `ssl_configured=false`, `provisioning_attempts=0`)
> 2. Lors de `/provision` : 
>    - `provisioning_attempts` est incrémenté (sauf si déjà provisionné = idempotent)
>    - `dns_configured` et `ssl_configured` sont mis à jour selon le résultat de `configureDNS()` et `configureSSL()`
>    - `infrastructure_status` est recalculé automatiquement
>    - `api_key_generated` reflète la présence d'une clé API
> 3. Les metadata sont toujours **à jour** car elles sont construites à partir des colonnes réelles à chaque requête
```

4. Vous stockez au minimum :
   - `uuid`,
   - `subdomain`,
   - `email`,
   - `organization_name`,
   - `onboarding_status`.

### 3.3. Gestion du processus côté client

- **Vous êtes maître du timing** :
  - provisionner immédiatement après `start`,
  - ou plus tard (jobs asynchrones, validations internes, paiement, etc.).
- Vous pouvez afficher un écran “nous préparons votre espace” pendant que vous appelez `/provision`.

### 3.4. Provisioning technique (DNS/SSL, API key)

Quand vous êtes prêt à provisionner :

```http
POST /api/v1/onboarding/provision
X-Master-Key: {votre_master_key}
Content-Type: application/json
```

```json
{
  "uuid": "550e8400-e29b-41d4-a716-446655440000",
  "generate_api_key": true
}
```

Réponse typique (enrichie avec metadata) :

```json
{
  "success": true,
  "uuid": "550e8400-e29b-41d4-a716-446655440000",
  "subdomain": "clinique-du-lac",
  "email": "admin@example.com",
  "organization_name": "Clinique du Lac",
  "onboarding_status": "activated",
  "api_key": "ak_abc123...",
  "api_secret": "ak_abc123...",
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

> **Note** : Si l'onboarding est déjà provisionné, `metadata.is_idempotent` sera `true` et `api_key`/`api_secret` seront `null`.

- `onboarding_status` est maintenant **“activated”** (READY).
- `api_key` / `api_secret` :
  - visibles **une seule fois**,
  - à stocker côté vous (vault / table chiffrée),
  - servent éventuellement à appeler d’autres APIs du microservice.

### 3.5. Création du tenant côté client

À partir des infos reçues (`subdomain`, `email`, `organization_name`, `uuid`) :

- Vous créez **votre tenant** dans votre propre système :
  - enregistrement tenant (table `tenants` ou équivalent),
  - utilisateur admin rattaché à ce tenant.
- Vous créez la **base de données du tenant** (si architecture multi-DB) :
  - par exemple : `db_{subdomain}` → `db_clinique_du_lac`.
  - Vous exécutez **vos migrations** (schéma métier).
- Vous gérez l’**envoi d’e-mails d’activation** :
  - génération de jeton d’activation côté vous,
  - envoi de l’e-mail,
  - activation via votre propre URL.

### 3.6. Finalisation de l'onboarding

- Utilisez `/onboarding/status/{uuid}` pour afficher un statut en temps réel à l'utilisateur ou à vos équipes internes.
- Une fois que votre tenant est prêt, **votre app** décide quand considérer l'onboarding comme "terminé" côté métier.

### 3.7. Signaler la complétion de l'onboarding

Quand votre application cliente a terminé la création du tenant et que tout est opérationnel, vous pouvez signaler la complétion au microservice :

```http
POST /api/v1/onboarding/{uuid}/complete
X-Master-Key: {votre_master_key}
Content-Type: application/json
```

```json
{
  "tenant_id": "tenant_123",
  "metadata": {
    "users_count": 1,
    "database_created": true
  }
}
```

**Réponse (Succès 200)** :

```json
{
  "success": true,
  "message": "Onboarding marqué comme complété avec succès",
  "uuid": "550e8400-e29b-41d4-a716-446655440000",
  "onboarding_status": "completed",
  "completed_at": "2026-02-07T10:40:00Z"
}
```

> **Note** : Cette étape est **optionnelle** mais recommandée pour le monitoring. Elle permet au microservice de suivre quels onboardings ont été complétés avec succès par les applications clientes.

---

## 4. FAQ

### Qui appelle le microservice ?

- **Votre backend** (application cliente), jamais directement le front.
- Les appels se font depuis un contexte sécurisé avec le header `X-Master-Key`.

### Où sont stockées les données ?

- Dans la **base centrale du microservice** :
  - uniquement : `email`, `organization_name`, `uuid`, `subdomain`, statut, metadata technique.
- Dans **vos bases** :
  - toutes les données métier, tenants, utilisateurs, logs métier, etc.

### Qui crée le tenant ?

- **Vous**, dans votre application :
  - création de la base,
  - création du tenant métier,
  - création des utilisateurs,
  - gestion des plans, droits, etc.

### Comment gérer les erreurs ?

- Codes HTTP :
  - `201` : création réussie (`/start`),
  - `200` : provisioning ou status OK,
  - `400/422` : erreurs de validation,
  - `401/403` : problème de clé (`X-Master-Key`) ou de droits,
  - `404` : `uuid` introuvable pour cette application,
  - `429` : rate limit dépassé (voir section Rate Limiting),
  - `500` : erreur interne côté microservice.
- Bonne pratique :
  - logger côté vous : `uuid`, URL, code HTTP, body réponse (sanitizé).
  - implémenter un backoff exponentiel pour les erreurs 429.
  - ne jamais logger les secrets (`api_key`, `api_secret`, `master_key`).

### Rate Limiting

Le microservice applique des limites de taux pour protéger l'infrastructure :

- `/start` : **10 requêtes/heure** par application
- `/provision` : **1 requête/24h** par UUID (tenant)
- `/status` : **100 requêtes/heure** par application
- Limite globale par IP : **50 requêtes/heure** (tous endpoints)

En cas de dépassement, vous recevrez un code `429 Too Many Requests` avec :
- Header `Retry-After` : nombre de secondes avant de pouvoir réessayer
- Header `X-RateLimit-Remaining` : nombre de requêtes restantes

**Recommandation** : Utilisez `/status` plutôt que `/provision` pour vérifier l'état (limite plus élevée).

### Que faire en cas d’interruption ?

- **Rejeu**
  - `/onboarding/start` → crée un **nouvel** onboarding.
  - `/onboarding/provision` → conçu pour être **idempotent** : rappel sans casser l’existant.
  - `/onboarding/status/{uuid}` → appel illimité.
- Stratégie :
  - conserver le `uuid` dans votre DB,
  - implémenter des retries avec backoff sur `/provision`.

### Sécurité et authentification

- Authentification via `X-Master-Key` :
  - obtenue via `/api/v1/applications/register` (sans création de base de données),
  - seule la master key est nécessaire pour démarrer un onboarding,
  - stockée côté serveur uniquement (hashée).
- Secrets (`master_key`, `api_key`, `api_secret`) :
  - stockés **hashés** côté microservice,
  - transmis en clair **une seule fois**,
  - à stocker de manière sécurisée côté vous.

### Scalabilité et haute disponibilité

- API **stateless** :
  - pas de sessions en mémoire,
  - toutes les infos utiles sont en base.
- Scalabilité horizontale :
  - plusieurs instances derrière un load balancer.

### Personnalisation du workflow

- Le microservice reste **générique** :
  - ne connaît pas votre métier,
  - ne connaît pas vos plans, modules, règles.
- Toute la personnalisation :
  - ordre des étapes,
  - validations métier,
  - emails,
  est chez vous.

---

## 5. Bonnes pratiques d’intégration

- **Gestion des statuts**
  - Utiliser `onboarding_status` comme indicateur **technique** seulement.
  - Avoir vos propres statuts métier côté vous.
- **Idempotence**
  - Traiter `/provision` comme “safe à rappeler”.
- **Rejeu**
  - Prévoir des retries sur `/provision` en cas d’incident infra.
- **Logs & monitoring**
  - Logger systématiquement le `uuid` côté vous pour corrélation avec les logs du microservice.

---

## 6. Exemples de payloads (récap)

### Démarrage

```http
POST /api/v1/onboarding/start
X-Master-Key: mk_...
Content-Type: application/json
```

```json
{
  "email": "admin@example.com",
  "organization_name": "Clinique du Lac"
}
```

### Provisioning

```http
POST /api/v1/onboarding/provision
X-Master-Key: mk_...
Content-Type: application/json
```

```json
{
  "uuid": "550e8400-e29b-41d4-a716-446655440000",
  "generate_api_key": true
}
```

### Statut

```http
GET /api/v1/onboarding/status/550e8400-e29b-41d4-a716-446655440000
X-Master-Key: mk_...
```

---

Ce guide peut être utilisé tel quel comme :

- documentation officielle d’intégration,
- support de présentation technique,
- base pour une page “Developer docs” dédiée à l’onboarding stateless.

