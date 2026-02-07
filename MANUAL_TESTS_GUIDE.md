# Guide de tests manuels - Microservice Onboarding Stateless

Ce guide décrit comment tester manuellement toutes les fonctionnalités du microservice d'onboarding stateless.

## Prérequis

1. **Application Laravel démarrée** : `php artisan serve` (ou via votre serveur web)
2. **Base de données configurée** : MySQL avec les migrations exécutées
3. **Outils** : 
   - Postman, cURL, ou un client HTTP
   - Navigateur web pour les interfaces
   - Accès à la base de données MySQL

## 1. Tests du Rate Limiting

### 1.1 Test du rate limiting sur `/start`

**Objectif** : Vérifier que l'endpoint `/start` limite à 10 requêtes/heure par application.

**Étapes** :

1. **Enregistrer une application** (si pas déjà fait) :
   ```bash
   POST http://127.0.0.1:8000/api/v1/applications/register
   Content-Type: application/json
   
   {
     "app_name": "test-app",
     "display_name": "Application de Test",
     "contact_email": "test@example.com"
   }
   ```
   
   **Résultat attendu** : Réponse avec `master_key` (à sauvegarder)

2. **Faire 10 requêtes à `/start`** :
   ```bash
   POST http://127.0.0.1:8000/api/v1/onboarding/start
   X-Master-Key: <votre_master_key>
   Content-Type: application/json
   
   {
     "email": "test1@example.com",
     "organization_name": "Test Org 1"
   }
   ```
   
   Répéter 10 fois avec des emails différents.

3. **Vérifier les headers de rate limiting** :
   - `X-RateLimit-Limit: 10`
   - `X-RateLimit-Remaining: 9, 8, 7, ... 0`
   - `X-RateLimit-Reset: <timestamp>`

4. **Faire la 11ème requête** :
   ```bash
   POST http://127.0.0.1:8000/api/v1/onboarding/start
   X-Master-Key: <votre_master_key>
   Content-Type: application/json
   
   {
     "email": "test11@example.com",
     "organization_name": "Test Org 11"
   }
   ```
   
   **Résultat attendu** :
   - Code HTTP : `429 Too Many Requests`
   - Body : `{"success": false, "message": "Trop de requêtes...", "retry_after_minutes": <nombre>}`
   - Headers : `Retry-After: <secondes>`

### 1.2 Test du rate limiting sur `/provision`

**Objectif** : Vérifier que l'endpoint `/provision` limite à 1 requête/24h par UUID.

**Étapes** :

1. **Créer un onboarding** :
   ```bash
   POST http://127.0.0.1:8000/api/v1/onboarding/start
   X-Master-Key: <votre_master_key>
   Content-Type: application/json
   
   {
     "email": "provision-test@example.com",
     "organization_name": "Provision Test"
   }
   ```
   
   Sauvegarder l'`uuid` retourné.

2. **Première tentative de provisioning** :
   ```bash
   POST http://127.0.0.1:8000/api/v1/onboarding/provision
   X-Master-Key: <votre_master_key>
   Content-Type: application/json
   
   {
     "uuid": "<uuid_obtenu>",
     "generate_api_key": true
   }
   ```
   
   **Résultat attendu** : Code `200` avec les données de provisioning

3. **Deuxième tentative immédiate** :
   Répéter la même requête.
   
   **Résultat attendu** :
   - Code HTTP : `429 Too Many Requests`
   - Message indiquant que la limite est atteinte

### 1.3 Test du rate limiting sur `/status`

**Objectif** : Vérifier que l'endpoint `/status` limite à 100 requêtes/heure.

**Étapes** :

1. Utiliser l'`uuid` d'un onboarding existant
2. Faire 100 requêtes GET à `/status/{uuid}`
3. Vérifier que la 101ème retourne `429`

## 2. Tests des Réponses Enrichies

### 2.1 Test de `/start` avec metadata

**Étapes** :

```bash
POST http://127.0.0.1:8000/api/v1/onboarding/start
X-Master-Key: <votre_master_key>
Content-Type: application/json

{
  "email": "metadata-test@example.com",
  "organization_name": "Metadata Test Org"
}
```

**Vérifications** :

- ✅ Réponse contient `metadata` :
  ```json
  {
    "success": true,
    "uuid": "...",
    "subdomain": "...",
    "metadata": {
      "created_at": "2026-02-07T...",
      "updated_at": "2026-02-07T...",
      "dns_configured": false,
      "ssl_configured": false,
      "infrastructure_status": "pending",
      "api_key_generated": false,
      "provisioning_attempts": 0
    }
  }
  ```

- ✅ `infrastructure_status` = `"pending"`
- ✅ `provisioning_attempts` = `0`
- ✅ `api_key_generated` = `false`

### 2.2 Test de `/provision` avec metadata

**Étapes** :

1. Créer un onboarding (voir 2.1)
2. Provisionner :
   ```bash
   POST http://127.0.0.1:8000/api/v1/onboarding/provision
   X-Master-Key: <votre_master_key>
   Content-Type: application/json
   
   {
     "uuid": "<uuid>",
     "generate_api_key": true
   }
   ```

**Vérifications** :

- ✅ Réponse contient `metadata` avec :
  - `infrastructure_status` = `"ready"` (si DNS et SSL OK) ou `"partial"`
  - `provisioning_attempts` = `1` (ou plus si retry)
  - `api_key_generated` = `true` (si `generate_api_key: true`)
  - `is_idempotent` = `false` (première fois)

### 2.3 Test de l'idempotence avec metadata

**Étapes** :

1. Provisionner un onboarding (voir 2.2)
2. **Re-provisionner le même UUID** :
   ```bash
   POST http://127.0.0.1:8000/api/v1/onboarding/provision
   X-Master-Key: <votre_master_key>
   Content-Type: application/json
   
   {
     "uuid": "<même_uuid>"
   }
   ```

**Vérifications** :

- ✅ Code HTTP : `200` (pas d'erreur)
- ✅ `metadata.is_idempotent` = `true`
- ✅ `api_key` et `api_secret` = `null` (pas régénérés)
- ✅ `onboarding_status` reste inchangé
- ✅ `provisioning_attempts` n'est **pas** incrémenté

## 3. Tests de Validation des Sous-domaines

### 3.1 Test de validation de format

**Étapes** :

Tester différents formats de sous-domaines via l'API (le service valide automatiquement) :

1. **Sous-domaine valide** :
   ```bash
   POST http://127.0.0.1:8000/api/v1/onboarding/start
   {
     "email": "valid@example.com",
     "organization_name": "Valid-Org-123"
   }
   ```
   
   **Résultat attendu** : Sous-domaine généré : `valid-org-123`

2. **Sous-domaine avec caractères spéciaux** :
   ```bash
   {
     "email": "special@example.com",
     "organization_name": "Org@#$%^&*()"
   }
   ```
   
   **Résultat attendu** : Sous-domaine nettoyé (caractères spéciaux supprimés)

3. **Sous-domaine réservé** :
   ```bash
   {
     "email": "admin@example.com",
     "organization_name": "admin"
   }
   ```
   
   **Résultat attendu** : Sous-domaine généré avec suffixe (ex: `admin-1`)

### 3.2 Test de l'unicité et retry automatique

**Étapes** :

1. **Créer un onboarding avec un sous-domaine spécifique** :
   ```bash
   POST /api/v1/onboarding/start
   {
     "email": "test1@example.com",
     "organization_name": "Test Organization"
   }
   ```
   
   Sauvegarder le `subdomain` retourné (ex: `test-organization`)

2. **Créer un deuxième onboarding avec le même nom** :
   ```bash
   POST /api/v1/onboarding/start
   {
     "email": "test2@example.com",
     "organization_name": "Test Organization"
   }
   ```
   
   **Résultat attendu** : Sous-domaine différent (ex: `test-organization-1`)

3. **Vérifier en base de données** :
   ```sql
   SELECT uuid, subdomain, email FROM onboarding_registrations 
   WHERE organization_name LIKE 'Test Organization%';
   ```
   
   **Vérifications** :
   - ✅ Tous les sous-domaines sont uniques
   - ✅ Format valide (minuscules, tirets uniquement)

### 3.3 Test de validation DNS (optionnel)

**Note** : Ce test nécessite que la vérification DNS soit activée dans `SubdomainService`.

**Étapes** :

1. Configurer `SubdomainService` pour activer la vérification DNS
2. Créer un onboarding
3. Vérifier les logs pour voir si la vérification DNS a été effectuée

## 4. Tests de l'Interface Web Self-Service

### 4.1 Test d'enregistrement d'application

**Étapes** :

1. **Accéder à la page de liste** (nouveau) :
   ```
   http://127.0.0.1:8000/applications
   ```
   
   Cette page permet de rechercher vos applications par email.

2. **Accéder au formulaire d'enregistrement** :
   ```
   http://127.0.0.1:8000/applications/register
   ```
   
   Ou cliquer sur "Enregistrer une nouvelle application" depuis la page de liste.

2. **Remplir le formulaire** :
   - Nom technique : `manual-test-app`
   - Nom d'affichage : `Application de Test Manuel`
   - Email de contact : `manual-test@example.com`
   - Site web (optionnel) : `https://example.com`

3. **Soumission** :
   - Cliquer sur "Enregistrer l'application"

**Vérifications** :

- ✅ Redirection vers le dashboard
- ✅ Message de succès affiché
- ✅ Master key affichée **une seule fois** (avec warning)
- ✅ Email envoyé avec la master key (vérifier la boîte mail)
- ✅ Base de données créée (si succès)

### 4.2 Test du dashboard d'application

**Étapes** :

1. **Trouver votre application** :
   - Accéder à `http://127.0.0.1:8000/applications`
   - Entrer votre email de contact
   - Cliquer sur "Rechercher"
   - Votre application apparaîtra avec son `app_id`

2. **Accéder au dashboard** :
   - Cliquer sur "Accéder au Dashboard" depuis la liste
   - Ou directement : `http://127.0.0.1:8000/applications/{app_id}/dashboard`
   
   Remplacez `{app_id}` par l'ID réel de votre application (ex: `app_abc123...`).

**Vérifications** :

- ✅ Statistiques affichées :
  - Total Onboardings
  - En attente
  - Activés
  - Annulés

- ✅ Liste des onboardings récents avec :
  - UUID (tronqué)
  - Email
  - Organisation
  - Sous-domaine
  - Statut (badge coloré)
  - Date de création

- ✅ Master key affichée si c'est la première visite après enregistrement
- ✅ Base de données affichée si créée avec succès

### 4.3 Test de la gestion des clés API

**Étapes** :

1. **Accéder à la page des clés API** :
   ```
   http://127.0.0.1:8000/applications/{app_id}/api-keys
   ```

**Vérifications** :

- ✅ Liste des clés API existantes (si créées via API)
- ✅ Instructions pour créer de nouvelles clés via API
- ✅ Informations sur l'utilisation de la master key

## 5. Tests du Dashboard de Monitoring Admin

### 5.1 Test d'accès au monitoring

**Étapes** :

1. **Se connecter en tant qu'admin** :
   ```
   http://127.0.0.1:8000/admin/login
   ```

2. **Accéder au monitoring** :
   ```
   http://127.0.0.1:8000/admin/monitoring/onboardings
   ```

**Vérifications** :

- ✅ Statistiques globales affichées :
  - Total
  - En attente
  - Activés
  - Annulés
  - Taux de succès
  - Temps moyen de provisioning

- ✅ Alertes pour onboardings bloqués (si > 24h en pending)

### 5.2 Test des filtres

**Étapes** :

1. **Filtrer par statut** :
   - Sélectionner "En attente" dans le filtre Statut
   - Cliquer sur "Filtrer"

2. **Filtrer par application** :
   - Sélectionner une application dans le filtre Application
   - Cliquer sur "Filtrer"

3. **Filtrer par date** :
   - Sélectionner une plage de dates
   - Cliquer sur "Filtrer"

4. **Recherche textuelle** :
   - Entrer un email, UUID, ou nom d'organisation
   - Cliquer sur "Filtrer"

**Vérifications** :

- ✅ Les résultats sont filtrés correctement
- ✅ Les filtres persistent dans l'URL
- ✅ Le nombre de résultats correspond aux filtres

### 5.3 Test de l'export CSV

**Étapes** :

1. **Appliquer des filtres** (optionnel)
2. **Cliquer sur "Exporter en CSV"**

**Vérifications** :

- ✅ Fichier CSV téléchargé
- ✅ Contenu du CSV :
  - En-têtes : UUID, Application, Email, Organisation, Sous-domaine, Statut, DNS, SSL, Tentatives, Dates
  - Données correspondant aux filtres appliqués
  - Format correct (virgules, guillemets si nécessaire)

### 5.4 Test du dashboard admin étendu

**Étapes** :

1. **Accéder au dashboard admin** :
   ```
   http://127.0.0.1:8000/admin/dashboard
   ```

**Vérifications** :

- ✅ Statistiques d'onboarding stateless affichées :
  - Total Onboardings Stateless
  - Actifs / En attente

- ✅ Alertes pour onboardings bloqués (si présents)
- ✅ Lien vers le monitoring des onboardings

## 6. Tests de l'Idempotence

### 6.1 Test de l'idempotence de `/provision`

**Étapes** :

1. **Créer un onboarding** :
   ```bash
   POST /api/v1/onboarding/start
   {
     "email": "idempotent-test@example.com",
     "organization_name": "Idempotent Test"
   }
   ```
   
   Sauvegarder l'`uuid`.

2. **Premier provisioning** :
   ```bash
   POST /api/v1/onboarding/provision
   {
     "uuid": "<uuid>",
     "generate_api_key": true
   }
   ```
   
   Sauvegarder :
   - `api_key` retourné
   - `onboarding_status`
   - `metadata.provisioning_attempts`

3. **Deuxième provisioning (idempotent)** :
   ```bash
   POST /api/v1/onboarding/provision
   {
     "uuid": "<même_uuid>"
   }
   ```

**Vérifications** :

- ✅ Code HTTP : `200` (pas d'erreur)
- ✅ `metadata.is_idempotent` = `true`
- ✅ `api_key` = `null` (pas régénéré)
- ✅ `onboarding_status` = identique au premier appel
- ✅ `metadata.provisioning_attempts` = identique (pas incrémenté)
- ✅ En base de données : `provisioning_attempts` n'a pas changé

## 7. Tests de Validation des Données

### 7.1 Test avec organisation_name optionnel

**Étapes** :

1. **Créer un onboarding SANS organisation_name** :
   ```bash
   POST /api/v1/onboarding/start
   {
     "email": "no-org@example.com"
   }
   ```

**Vérifications** :

- ✅ Code HTTP : `201` (succès)
- ✅ `organization_name` généré automatiquement (basé sur l'email)
- ✅ Sous-domaine généré basé sur l'email

2. **Créer un onboarding AVEC organisation_name** :
   ```bash
   POST /api/v1/onboarding/start
   {
     "email": "with-org@example.com",
     "organization_name": "Mon Organisation"
   }
   ```

**Vérifications** :

- ✅ `organization_name` = `"Mon Organisation"`
- ✅ Sous-domaine basé sur `organization_name`

### 7.2 Test de validation des emails

**Étapes** :

1. **Email invalide** :
   ```bash
   POST /api/v1/onboarding/start
   {
     "email": "email-invalide",
     "organization_name": "Test"
   }
   ```

**Résultat attendu** :
- Code HTTP : `422 Unprocessable Entity`
- Body : `{"success": false, "errors": {"email": ["The email must be a valid email address."]}}`

## 8. Tests de Monitoring et Observabilité

### 8.1 Vérification des logs

**Étapes** :

1. **Créer plusieurs onboardings**
2. **Vérifier les logs Laravel** :
   ```bash
   tail -f storage/logs/laravel.log
   ```

**Vérifications** :

- ✅ Logs pour chaque onboarding créé
- ✅ Logs pour les tentatives de provisioning
- ✅ Logs pour les conflits de sous-domaines (si présents)
- ✅ Logs pour les erreurs de rate limiting

### 8.2 Vérification en base de données

**Étapes** :

1. **Vérifier la table `onboarding_registrations`** :
   ```sql
   SELECT 
     uuid,
     email,
     organization_name,
     subdomain,
     status,
     dns_configured,
     ssl_configured,
     provisioning_attempts,
     created_at,
     updated_at
   FROM onboarding_registrations
   ORDER BY created_at DESC
   LIMIT 10;
   ```

**Vérifications** :

- ✅ Tous les champs sont remplis correctement
- ✅ `provisioning_attempts` est incrémenté à chaque provisioning (sauf si idempotent)
- ✅ `dns_configured` et `ssl_configured` sont mis à jour lors du provisioning
- ✅ `status` change de `pending` à `activated` ou `cancelled`

## 9. Checklist de Validation Complète

### Rate Limiting
- [ ] `/start` limite à 10 requêtes/heure
- [ ] `/provision` limite à 1 requête/24h par UUID
- [ ] `/status` limite à 100 requêtes/heure
- [ ] Headers de rate limiting présents (`X-RateLimit-*`)
- [ ] Code 429 retourné quand limite dépassée
- [ ] `Retry-After` header présent

### Réponses Enrichies
- [ ] `/start` retourne `metadata` complète
- [ ] `/provision` retourne `metadata` complète
- [ ] `/status` retourne `metadata` complète
- [ ] `infrastructure_status` calculé correctement
- [ ] `provisioning_attempts` incrémenté correctement
- [ ] `is_idempotent` présent dans `/provision`

### Validation Sous-domaines
- [ ] Format validé (minuscules, tirets uniquement)
- [ ] Sous-domaines réservés détectés
- [ ] Unicité garantie
- [ ] Retry automatique avec suffixe
- [ ] Conflits gérés (logs si présents)

### Interface Web
- [ ] Formulaire d'enregistrement fonctionne
- [ ] Master key affichée une seule fois
- [ ] Email envoyé avec master key
- [ ] Dashboard affiche les statistiques
- [ ] Liste des onboardings fonctionne
- [ ] Gestion des clés API accessible

### Dashboard Admin
- [ ] Statistiques d'onboarding affichées
- [ ] Alertes pour onboardings bloqués
- [ ] Filtres fonctionnent
- [ ] Export CSV fonctionne
- [ ] Monitoring accessible

### Idempotence
- [ ] `/provision` idempotent (appels multiples = même résultat)
- [ ] `is_idempotent` = `true` lors d'appels répétés
- [ ] `provisioning_attempts` non incrémenté si idempotent

## 10. Scénarios de Test Complets

### Scénario 1 : Flux complet d'onboarding

1. Enregistrer une application via l'interface web
2. Utiliser la master key pour créer un onboarding via API
3. Vérifier les metadata dans la réponse
4. Provisionner l'onboarding
5. Vérifier l'idempotence en re-provisionnant
6. Vérifier le statut final
7. Consulter le dashboard admin pour voir l'onboarding

### Scénario 2 : Gestion des erreurs

1. Tester avec email invalide → `422`
2. Tester avec master key invalide → `401`
3. Tester avec UUID inexistant → `404`
4. Dépasser les limites de rate limiting → `429`
5. Vérifier que les erreurs sont loggées

### Scénario 3 : Performance et limites

1. Créer 10 onboardings rapidement
2. Vérifier que le 11ème est bloqué par rate limiting
3. Attendre 1 heure (ou modifier la limite pour tester)
4. Vérifier que les requêtes fonctionnent à nouveau

## Notes

- **Base de données de test** : Utilisez une base de données séparée pour les tests manuels si possible
- **Nettoyage** : Supprimez les données de test après validation
- **Logs** : Surveillez les logs pour détecter les erreurs non visibles dans les réponses
- **Performance** : Notez les temps de réponse pour identifier les problèmes de performance

## Commandes utiles pour les tests

```bash
# Voir les onboardings en base
mysql -u root -p -e "SELECT uuid, email, subdomain, status, provisioning_attempts FROM onboarding_registrations ORDER BY created_at DESC LIMIT 10;" <database_name>

# Voir les applications
mysql -u root -p -e "SELECT app_id, app_name, display_name, is_active FROM applications;" <database_name>

# Voir les logs en temps réel
tail -f storage/logs/laravel.log

# Nettoyer les données de test
mysql -u root -p -e "DELETE FROM onboarding_registrations WHERE email LIKE 'test%@example.com';" <database_name>
```
