# Scénarios de Test Manuels - Cas d'Usage Réels

## Scénario 1 : Application cliente intègre le microservice

**Contexte** : Une application cliente (ex: Ejustice) veut intégrer le microservice d'onboarding.

### Étape 1 : Enregistrement de l'application

1. Accéder à `http://127.0.0.1:8000/applications/register`
2. Remplir :
   - Nom technique : `ejustice`
   - Nom d'affichage : `Ejustice - Gestion Judiciaire`
   - Email : `dev@ejustice.com`
3. Soumettre

**Résultats attendus** :
- ✅ Redirection vers dashboard
- ✅ Master key affichée : `mk_...` (48 caractères)
- ✅ Email reçu avec master key
- ✅ Base de données créée : `app_ejustice_db`

**Vérifications** :
```sql
SELECT app_id, app_name, master_key IS NOT NULL as has_master_key 
FROM applications WHERE app_name = 'ejustice';
```

### Étape 2 : Premier onboarding via API

```bash
POST http://127.0.0.1:8000/api/v1/onboarding/start
X-Master-Key: mk_<votre_master_key>
Content-Type: application/json

{
  "email": "admin@cabinet-avocat.fr",
  "organization_name": "Cabinet Martin & Associés"
}
```

**Résultats attendus** :
- ✅ Code `201 Created`
- ✅ `uuid` retourné
- ✅ `subdomain` : `cabinet-martin-associes` (ou avec suffixe si existe)
- ✅ `metadata.infrastructure_status` = `"pending"`
- ✅ `metadata.provisioning_attempts` = `0`

**Vérifications en base** :
```sql
SELECT uuid, subdomain, status, provisioning_attempts 
FROM onboarding_registrations 
WHERE email = 'admin@cabinet-avocat.fr';
```

### Étape 3 : Provisioning de l'infrastructure

```bash
POST http://127.0.0.1:8000/api/v1/onboarding/provision
X-Master-Key: mk_<votre_master_key>
Content-Type: application/json

{
  "uuid": "<uuid_obtenu>",
  "generate_api_key": true
}
```

**Résultats attendus** :
- ✅ Code `200 OK`
- ✅ `onboarding_status` = `"activated"`
- ✅ `api_key` retourné (une seule fois)
- ✅ `metadata.infrastructure_status` = `"ready"`
- ✅ `metadata.provisioning_attempts` = `1`
- ✅ `metadata.dns_configured` = `true`
- ✅ `metadata.ssl_configured` = `true`

### Étape 4 : Vérification du statut

```bash
GET http://127.0.0.1:8000/api/v1/onboarding/status/<uuid>
X-Master-Key: mk_<votre_master_key>
```

**Résultats attendus** :
- ✅ Code `200 OK`
- ✅ Toutes les informations de l'onboarding
- ✅ Metadata complète

### Étape 5 : Test d'idempotence

**Re-provisionner le même UUID** :
```bash
POST http://127.0.0.1:8000/api/v1/onboarding/provision
X-Master-Key: mk_<votre_master_key>
Content-Type: application/json

{
  "uuid": "<même_uuid>"
}
```

**Résultats attendus** :
- ✅ Code `200 OK` (pas d'erreur)
- ✅ `metadata.is_idempotent` = `true`
- ✅ `api_key` = `null` (pas régénéré)
- ✅ `metadata.provisioning_attempts` = `1` (non incrémenté)

## Scénario 2 : Gestion des erreurs et limites

### Test 1 : Rate limiting sur /start

**Objectif** : Vérifier que le rate limiting fonctionne.

1. Faire 10 requêtes à `/start` avec la même master key
2. Vérifier que les headers `X-RateLimit-Remaining` diminuent
3. Faire la 11ème requête

**Résultat attendu** : `429 Too Many Requests` avec `Retry-After`

### Test 2 : Validation des données

**Email invalide** :
```bash
POST /api/v1/onboarding/start
{
  "email": "email-invalide",
  "organization_name": "Test"
}
```

**Résultat attendu** : `422` avec erreur de validation

**Master key invalide** :
```bash
POST /api/v1/onboarding/start
X-Master-Key: mk_invalide
{
  "email": "test@example.com"
}
```

**Résultat attendu** : `401 Unauthorized`

**UUID inexistant** :
```bash
GET /api/v1/onboarding/status/00000000-0000-0000-0000-000000000000
X-Master-Key: mk_<valide>
```

**Résultat attendu** : `404 Not Found`

## Scénario 3 : Organisation name optionnel

### Test 1 : Sans organisation_name

```bash
POST /api/v1/onboarding/start
{
  "email": "sans-org@example.com"
}
```

**Vérifications** :
- ✅ `organization_name` généré automatiquement
- ✅ Basé sur l'email (partie locale)
- ✅ Sous-domaine généré basé sur l'email

### Test 2 : Avec organisation_name

```bash
POST /api/v1/onboarding/start
{
  "email": "avec-org@example.com",
  "organization_name": "Ma Super Organisation"
}
```

**Vérifications** :
- ✅ `organization_name` = `"Ma Super Organisation"`
- ✅ Sous-domaine = `ma-super-organisation` (ou avec suffixe)

## Scénario 4 : Conflits de sous-domaines

### Test : Génération avec retry automatique

1. Créer un onboarding avec `organization_name: "Test Org"`
   - Sous-domaine généré : `test-org`

2. Créer un deuxième onboarding avec le même nom
   - Sous-domaine généré : `test-org-1`

3. Créer un troisième onboarding avec le même nom
   - Sous-domaine généré : `test-org-2`

**Vérifications** :
- ✅ Tous les sous-domaines sont uniques
- ✅ Format valide
- ✅ Pas d'erreur de conflit

## Scénario 5 : Dashboard Admin - Monitoring

### Test 1 : Vue globale

1. Se connecter en admin
2. Accéder à `/admin/monitoring/onboardings`

**Vérifications** :
- ✅ Tous les onboardings affichés
- ✅ Statistiques correctes
- ✅ Alertes pour onboardings bloqués (si présents)

### Test 2 : Filtres

1. Filtrer par statut "pending"
2. Filtrer par application
3. Filtrer par date
4. Rechercher par email

**Vérifications** :
- ✅ Résultats filtrés correctement
- ✅ Filtres persistent dans l'URL

### Test 3 : Export CSV

1. Appliquer des filtres
2. Cliquer sur "Exporter en CSV"

**Vérifications** :
- ✅ Fichier téléchargé
- ✅ Contenu correspond aux filtres
- ✅ Format CSV valide

## Scénario 6 : Interface Web Self-Service

### Test 1 : Enregistrement

1. Accéder à `/applications/register`
2. Remplir le formulaire
3. Soumettre

**Vérifications** :
- ✅ Redirection vers dashboard
- ✅ Master key affichée
- ✅ Email envoyé
- ✅ Base de données créée (si succès)

### Test 2 : Dashboard

1. Accéder à `/applications/{app_id}/dashboard`

**Vérifications** :
- ✅ Statistiques affichées
- ✅ Liste des onboardings
- ✅ Informations de base de données (si créée)

## Checklist Rapide

### API Endpoints
- [ ] `POST /api/v1/onboarding/start` - Création avec metadata
- [ ] `POST /api/v1/onboarding/provision` - Provisioning avec idempotence
- [ ] `GET /api/v1/onboarding/status/{uuid}` - Statut avec metadata
- [ ] Rate limiting fonctionne sur tous les endpoints
- [ ] Headers de rate limiting présents

### Validation
- [ ] `organization_name` optionnel (génération automatique)
- [ ] Validation des emails
- [ ] Validation des sous-domaines
- [ ] Gestion des conflits

### Interface Web
- [ ] Enregistrement d'application
- [ ] Dashboard d'application
- [ ] Gestion des clés API

### Dashboard Admin
- [ ] Vue globale des onboardings
- [ ] Filtres fonctionnels
- [ ] Export CSV
- [ ] Statistiques en temps réel

### Observabilité
- [ ] Logs pour chaque action
- [ ] Metadata dans les réponses
- [ ] Tracking des tentatives de provisioning
