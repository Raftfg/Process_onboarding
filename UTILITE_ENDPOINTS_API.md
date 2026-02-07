# Utilité des Endpoints API Non Documentés

Ce document explique à quoi servent les endpoints qui ne sont pas encore documentés dans Swagger.

---

## 📱 Endpoints Applications

### 1. `POST /api/v1/applications/register`
**Utilité** : Point d'entrée principal pour les applications clientes

**Ce qu'il fait** :
- Permet à une nouvelle application cliente de s'enregistrer dans le système
- Génère automatiquement une **master key** unique (affichée une seule fois)
- Crée une base de données dédiée pour l'application (optionnel)
- Retourne les credentials de la base de données (si créée)

**Quand l'utiliser** :
- Lorsqu'une nouvelle application veut intégrer votre API d'onboarding
- Premier appel avant d'utiliser les autres endpoints

**Exemple d'utilisation** :
```bash
POST /api/v1/applications/register
{
  "app_name": "mon-application",
  "display_name": "Mon Application",
  "contact_email": "dev@monapp.com",
  "website": "https://monapp.com"
}
```

**Réponse** :
- `app_id` : Identifiant unique de l'application
- `master_key` : Clé secrète pour authentifier toutes les requêtes (⚠️ à sauvegarder immédiatement)
- `database` : Informations de connexion à la base de données (si créée)

---

### 2. `POST /api/v1/applications/regenerate-master-key`
**Utilité** : Régénérer la master key en cas de perte ou compromission

**Ce qu'il fait** :
- Génère une nouvelle master key pour une application existante
- Invalide l'ancienne master key
- Vérifie l'identité via `app_name` + `contact_email` (sécurité)

**Quand l'utiliser** :
- Si la master key a été perdue ou compromise
- Pour rotation de sécurité périodique

**Exemple d'utilisation** :
```bash
POST /api/v1/applications/regenerate-master-key
{
  "app_name": "mon-application",
  "contact_email": "dev@monapp.com"
}
```

**⚠️ Important** : L'ancienne master key devient immédiatement invalide après régénération.

---

### 3. `GET /api/v1/applications/{app_id}`
**Utilité** : Récupérer les informations de son application

**Ce qu'il fait** :
- Retourne les détails de l'application (nom, email, statut, dates)
- Permet de vérifier que l'application est active
- Affiche la date de dernière utilisation

**Quand l'utiliser** :
- Pour vérifier le statut de son application
- Pour obtenir les informations de contact
- Pour le monitoring/dashboard de l'application cliente

**Exemple d'utilisation** :
```bash
GET /api/v1/applications/app_abc123
Headers: X-Master-Key: mk_live_xyz789...
```

**Réponse** :
- Informations de l'application (app_id, app_name, display_name, contact_email)
- Statut (`is_active`)
- Dates (`created_at`, `last_used_at`)

---

### 4. `POST /api/v1/applications/{app_id}/retry-database`
**Utilité** : Réessayer la création de la base de données si elle a échoué

**Ce qu'il fait** :
- Tente de créer la base de données pour une application qui n'en a pas encore
- Retourne les credentials de connexion (affichés une seule fois)
- Utile si la création initiale a échoué lors de l'enregistrement

**Quand l'utiliser** :
- Si lors de l'enregistrement (`/register`), la création de la base de données a échoué
- Pour créer la base de données après coup

**Exemple d'utilisation** :
```bash
POST /api/v1/applications/app_abc123/retry-database
Headers: X-Master-Key: mk_live_xyz789...
```

**Réponse** :
- Credentials de la base de données (host, port, username, password, connection_string)
- ⚠️ Le mot de passe n'est affiché qu'une seule fois

---

## 🔑 Gestion des Clés API

### 5. `GET /api/v1/applications/{app_id}/api-keys`
**Utilité** : Lister toutes les clés API créées par l'application

**Ce qu'il fait** :
- Affiche la liste de toutes les clés API générées
- Montre le préfixe de chaque clé (pas la clé complète pour sécurité)
- Affiche le statut (active/inactive), les limites de taux, les dates d'expiration

**Quand l'utiliser** :
- Pour voir combien de clés API sont actives
- Pour vérifier les limites de taux configurées
- Pour le monitoring et la gestion

**Exemple d'utilisation** :
```bash
GET /api/v1/applications/app_abc123/api-keys
Headers: X-Master-Key: mk_live_xyz789...
```

**Réponse** :
- Liste des clés avec : `id`, `name`, `key_prefix`, `is_active`, `rate_limit`, `expires_at`, `last_used_at`

---

### 6. `POST /api/v1/applications/{app_id}/api-keys`
**Utilité** : Créer une nouvelle clé API pour des usages spécifiques

**Ce qu'il fait** :
- Génère une nouvelle clé API avec un nom personnalisé
- Permet de configurer des limites de taux spécifiques
- Peut avoir une date d'expiration
- Utile pour créer des clés dédiées à différents environnements (dev, staging, prod)

**Quand l'utiliser** :
- Pour créer des clés API séparées pour différents environnements
- Pour avoir des limites de taux différentes par clé
- Pour la rotation de clés (créer une nouvelle avant de révoquer l'ancienne)

**Exemple d'utilisation** :
```bash
POST /api/v1/applications/app_abc123/api-keys
Headers: X-Master-Key: mk_live_xyz789...
{
  "name": "Production Key",
  "rate_limit": 1000,
  "expires_at": "2026-12-31T23:59:59Z"
}
```

**Réponse** :
- La clé API complète (affichée une seule fois)
- ⚠️ À sauvegarder immédiatement

---

### 7. `GET /api/v1/applications/{app_id}/api-keys/{key_id}`
**Utilité** : Voir les détails d'une clé API spécifique

**Ce qu'il fait** :
- Affiche les informations détaillées d'une clé API
- Montre la configuration actuelle (rate_limit, expires_at, api_config)
- Affiche la date de dernière utilisation

**Quand l'utiliser** :
- Pour vérifier la configuration d'une clé
- Pour voir quand une clé a été utilisée pour la dernière fois
- Pour le debugging

**Exemple d'utilisation** :
```bash
GET /api/v1/applications/app_abc123/api-keys/5
Headers: X-Master-Key: mk_live_xyz789...
```

---

### 8. `PUT /api/v1/applications/{app_id}/api-keys/{key_id}/config`
**Utilité** : Configurer le comportement d'une clé API

**Ce qu'il fait** :
- Modifie la configuration d'une clé API
- Permet de configurer :
  - Si le nom d'organisation est requis
  - La stratégie de génération du nom d'organisation (auto, email, timestamp, etc.)
  - Le template personnalisé pour générer le nom

**Quand l'utiliser** :
- Pour personnaliser le comportement de l'API selon les besoins
- Pour changer la stratégie de génération des noms d'organisation
- Pour activer/désactiver certaines validations

**Exemple d'utilisation** :
```bash
PUT /api/v1/applications/app_abc123/api-keys/5/config
Headers: X-Master-Key: mk_live_xyz789...
{
  "require_organization_name": false,
  "organization_name_generation_strategy": "email",
  "organization_name_template": null
}
```

---

### 9. `DELETE /api/v1/applications/{app_id}/api-keys/{key_id}`
**Utilité** : Révoquer (désactiver) une clé API

**Ce qu'il fait** :
- Désactive une clé API (ne la supprime pas pour l'audit)
- La clé devient immédiatement inutilisable
- Utile pour la sécurité en cas de compromission

**Quand l'utiliser** :
- Si une clé a été compromise
- Pour désactiver une clé qui n'est plus utilisée
- Pour la rotation de clés (créer une nouvelle, puis révoquer l'ancienne)

**Exemple d'utilisation** :
```bash
DELETE /api/v1/applications/app_abc123/api-keys/5
Headers: X-Master-Key: mk_live_xyz789...
```

**⚠️ Important** : La clé est immédiatement désactivée et ne peut plus être utilisée.

---

## 🔔 Webhooks

### 10. `POST /api/webhooks/register`
**Utilité** : Enregistrer un webhook pour recevoir des notifications

**Ce qu'il fait** :
- Enregistre une URL qui recevra des notifications lors d'événements
- Génère un secret pour vérifier l'authenticité des webhooks
- Configure les événements à écouter (onboarding.completed, onboarding.failed, test)

**Quand l'utiliser** :
- Pour être notifié automatiquement quand un onboarding est complété
- Pour déclencher des actions dans votre application quand un événement se produit
- Pour l'intégration asynchrone

**Exemple d'utilisation** :
```bash
POST /api/webhooks/register
Headers: X-API-Key: votre-api-key
{
  "url": "https://monapp.com/webhooks/onboarding",
  "events": ["onboarding.completed", "onboarding.failed"],
  "timeout": 30
}
```

**Réponse** :
- `id` : ID du webhook
- `secret` : Secret pour vérifier la signature (⚠️ à sauvegarder)

**Événements disponibles** :
- `onboarding.completed` : Quand un onboarding est complété
- `onboarding.failed` : Quand un onboarding échoue
- `test` : Pour tester le webhook

---

### 11. `GET /api/webhooks`
**Utilité** : Lister tous les webhooks enregistrés

**Ce qu'il fait** :
- Affiche la liste de tous les webhooks configurés
- Montre l'URL, les événements, le statut (actif/inactif)
- Affiche la date de dernier déclenchement

**Quand l'utiliser** :
- Pour voir tous les webhooks configurés
- Pour vérifier qu'un webhook est actif
- Pour le monitoring

**Exemple d'utilisation** :
```bash
GET /api/webhooks
Headers: X-API-Key: votre-api-key
```

**Filtres optionnels** :
- `?api_key_id=5` : Filtrer par clé API

---

### 12. `POST /api/webhooks/test`
**Utilité** : Tester que les webhooks fonctionnent correctement

**Ce qu'il fait** :
- Déclenche un événement de test vers tous les webhooks actifs
- Permet de vérifier que votre endpoint reçoit bien les notifications
- Utile pour le debugging

**Quand l'utiliser** :
- Après avoir enregistré un webhook pour vérifier qu'il fonctionne
- Pour tester la configuration de votre endpoint
- Pour le debugging

**Exemple d'utilisation** :
```bash
POST /api/webhooks/test
Headers: X-API-Key: votre-api-key
```

**Réponse** :
- Confirmation que les webhooks de test ont été déclenchés

---

### 13. `DELETE /api/webhooks/{id}`
**Utilité** : Désactiver un webhook

**Ce qu'il fait** :
- Désactive un webhook (ne le supprime pas pour l'audit)
- Le webhook ne recevra plus de notifications
- Utile pour arrêter temporairement les notifications

**Quand l'utiliser** :
- Si vous ne voulez plus recevoir de notifications pour un webhook
- Pour désactiver temporairement un webhook en maintenance
- Pour nettoyer les webhooks non utilisés

**Exemple d'utilisation** :
```bash
DELETE /api/webhooks/10
Headers: X-API-Key: votre-api-key
```

---

## 📊 Résumé par Catégorie

### 🔐 Authentification et Gestion d'Application
- **`/register`** : Point d'entrée pour s'enregistrer
- **`/regenerate-master-key`** : Régénérer la clé principale
- **`GET /{app_id}`** : Voir les infos de son application
- **`/retry-database`** : Créer la base de données si échec

### 🔑 Gestion des Clés API
- **`GET /api-keys`** : Lister les clés
- **`POST /api-keys`** : Créer une nouvelle clé
- **`GET /api-keys/{id}`** : Voir les détails d'une clé
- **`PUT /api-keys/{id}/config`** : Configurer une clé
- **`DELETE /api-keys/{id}`** : Révoquer une clé

### 🔔 Webhooks (Notifications)
- **`POST /register`** : Enregistrer un webhook
- **`GET /`** : Lister les webhooks
- **`POST /test`** : Tester les webhooks
- **`DELETE /{id}`** : Désactiver un webhook

---

## 🎯 Cas d'Usage Typiques

### Scénario 1 : Nouvelle Application
1. `POST /applications/register` → Obtenir master_key
2. `GET /applications/{app_id}` → Vérifier les infos
3. `POST /applications/{app_id}/api-keys` → Créer des clés API pour dev/prod
4. `POST /webhooks/register` → Configurer les notifications

### Scénario 2 : Rotation de Sécurité
1. `POST /applications/{app_id}/api-keys` → Créer nouvelle clé
2. Mettre à jour l'application pour utiliser la nouvelle clé
3. `DELETE /applications/{app_id}/api-keys/{old_id}` → Révoquer l'ancienne

### Scénario 3 : Monitoring
1. `GET /applications/{app_id}` → Vérifier le statut
2. `GET /applications/{app_id}/api-keys` → Voir l'utilisation des clés
3. `GET /webhooks` → Vérifier que les webhooks fonctionnent

---

**Note** : Ces endpoints sont fonctionnels mais ne sont pas encore documentés dans Swagger. Ils peuvent être utilisés via l'API, mais il faudrait ajouter les annotations OpenAPI pour qu'ils apparaissent dans la documentation interactive.
