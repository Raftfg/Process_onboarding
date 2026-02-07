# 🔐 Explication des Clés d'Authentification

## Vue d'ensemble

L'API utilise **deux types de clés d'authentification** pour sécuriser l'accès aux différents endpoints :

1. **MasterKey** (`X-Master-Key`) - Clé principale de l'application
2. **ApiKey** (`X-API-Key`) - Clés API secondaires générées

---

## 🔑 1. MasterKey (Clé Maître)

### 📋 Description
La **MasterKey** est la clé principale qui identifie votre application cliente. C'est la première clé que vous obtenez lors de l'enregistrement de votre application.

### 🎯 Utilisation
- **Header** : `X-Master-Key`
- **Format** : `mk_` suivi de 48 caractères aléatoires
- **Exemple** : `mk_live_xyz789abcdef1234567890abcdef1234567890abcdef1234567890`

### 📍 Endpoints qui utilisent MasterKey

#### Onboarding Stateless
- `POST /api/v1/onboarding/start` - Démarrer un onboarding
- `POST /api/v1/onboarding/provision` - Provisionner l'infrastructure
- `GET /api/v1/onboarding/status/{uuid}` - Vérifier le statut
- `POST /api/v1/onboarding/{uuid}/complete` - Marquer comme complété

#### Gestion des Applications
- `GET /api/v1/applications/{app_id}` - Récupérer les infos de l'application
- `POST /api/v1/applications/{app_id}/retry-database` - Réessayer la création de la base

#### Gestion des Clés API
- `GET /api/v1/applications/{app_id}/api-keys` - Lister les clés API
- `POST /api/v1/applications/{app_id}/api-keys` - Créer une nouvelle clé API
- `GET /api/v1/applications/{app_id}/api-keys/{key_id}` - Détails d'une clé
- `PUT /api/v1/applications/{app_id}/api-keys/{key_id}/config` - Configurer une clé
- `DELETE /api/v1/applications/{app_id}/api-keys/{key_id}` - Révoquer une clé

### 🔄 Comment obtenir une MasterKey ?

#### Étape 1 : Enregistrer votre application
```http
POST /api/v1/applications/register
Content-Type: application/json

{
  "app_name": "mon-application",
  "display_name": "Mon Application",
  "contact_email": "dev@monapp.com",
  "website": "https://monapp.com"
}
```

#### Réponse :
```json
{
  "success": true,
  "message": "Application enregistrée avec succès",
  "application": {
    "app_id": "app_abc123...",
    "app_name": "mon-application",
    "display_name": "Mon Application",
    "contact_email": "dev@monapp.com",
    "website": "https://monapp.com",
    "created_at": "2026-02-07T10:30:00Z"
  },
  "master_key": "mk_live_xyz789...",  // ⚠️ À sauvegarder immédiatement !
  "warnings": [
    "⚠️ IMPORTANT: Sauvegardez la master_key immédiatement ! Elle ne sera plus jamais affichée.",
    "💡 Vous pouvez maintenant utiliser cette master_key pour démarrer un onboarding avec POST /api/v1/onboarding/start"
  ]
}
```

**Note importante** : L'enregistrement d'une application ne crée **pas** de base de données. Seule la master key est nécessaire pour démarrer un onboarding.

⚠️ **IMPORTANT** : La master key n'est affichée qu'**une seule fois** lors de l'enregistrement. Si vous la perdez, vous devrez la régénérer.

#### Étape 2 : Régénérer la MasterKey (si perdue)
```http
POST /api/v1/applications/regenerate-master-key
Content-Type: application/json

{
  "app_name": "mon-application",
  "contact_email": "dev@monapp.com"
}
```

### 🔒 Sécurité
- **Stockage** : Hashée dans la base de données (bcrypt)
- **Validation** : Vérifie que l'application est active
- **Vérification** : L'`app_id` dans l'URL doit correspondre à l'application
- **Accès** : Accès complet à tous les endpoints de gestion

### 📊 Caractéristiques
- ✅ **Unique par application** : Une seule master key par application
- ✅ **Pouvoirs étendus** : Permet de créer et gérer des clés API
- ✅ **Non expirable** : Ne peut pas expirer (mais peut être régénérée)
- ✅ **Identifie l'application** : Lie toutes les actions à votre application

---

## 🔑 2. ApiKey (Clés API Secondaires)

### 📋 Description
Les **ApiKeys** sont des clés API secondaires que vous créez à partir de votre MasterKey. Elles permettent une gestion plus granulaire des accès et peuvent être configurées avec des restrictions.

### 🎯 Utilisation
- **Header** : `X-API-Key` (ou `Authorization: Bearer <key>`)
- **Format** : `ak_live_` ou `ak_test_` suivi de caractères aléatoires
- **Exemple** : `ak_live_abc123def456ghi789jkl012mno345pqr678stu901vwx234`

### 📍 Endpoints qui utilisent ApiKey

#### Webhooks
- `POST /api/webhooks/register` - Enregistrer un webhook
- `GET /api/webhooks` - Lister les webhooks
- `POST /api/webhooks/test` - Tester les webhooks
- `DELETE /api/webhooks/{id}` - Désactiver un webhook

### 🔄 Comment créer une ApiKey ?

#### Étape 1 : Utiliser votre MasterKey pour créer une ApiKey
```http
POST /api/v1/applications/{app_id}/api-keys
X-Master-Key: mk_live_xyz789...
Content-Type: application/json

{
  "name": "Production Key",
  "rate_limit": 1000,
  "expires_at": "2026-12-31T23:59:59Z"
}
```

#### Réponse :
```json
{
  "success": true,
  "message": "Clé API créée avec succès",
  "api_key": {
    "id": 1,
    "key": "ak_live_abc123...",  // ⚠️ À sauvegarder immédiatement !
    "key_prefix": "ak_live_abc...",
    "name": "Production Key",
    "rate_limit": 1000,
    "expires_at": "2026-12-31T23:59:59Z"
  },
  "warning": "⚠️ IMPORTANT: Sauvegardez cette clé immédiatement !"
}
```

⚠️ **IMPORTANT** : La clé API complète n'est affichée qu'**une seule fois** lors de la création. Ensuite, seul le préfixe est visible.

### 🔒 Sécurité et Restrictions

#### 1. **Restriction par IP**
Vous pouvez limiter l'utilisation d'une clé API à certaines adresses IP :
```json
{
  "name": "Production Key",
  "allowed_ips": ["192.168.1.100", "10.0.0.50"]
}
```

#### 2. **Restriction par Application**
Si la clé est liée à une application spécifique, vous devez inclure le header `X-App-Name` :
```http
X-API-Key: ak_live_abc123...
X-App-Name: mon-application
```

#### 3. **Rate Limiting**
Chaque clé API peut avoir sa propre limite de requêtes par minute (1-10000).

#### 4. **Expiration**
Les clés API peuvent avoir une date d'expiration optionnelle.

#### 5. **Révocation**
Vous pouvez révoquer une clé API à tout moment sans affecter les autres clés.

### 📊 Caractéristiques
- ✅ **Multiples clés** : Vous pouvez créer plusieurs clés API
- ✅ **Granularité** : Chaque clé peut avoir ses propres restrictions
- ✅ **Expirable** : Peut avoir une date d'expiration
- ✅ **Révoquable** : Peut être désactivée individuellement
- ✅ **Usage limité** : Principalement pour les webhooks et intégrations externes

---

## 📊 Comparaison des Deux Clés

| Caractéristique | MasterKey | ApiKey |
|----------------|-----------|--------|
| **Obtention** | Via `/api/v1/applications/register` | Via `/api/v1/applications/{app_id}/api-keys` (avec MasterKey) |
| **Quantité** | 1 par application | Plusieurs par application |
| **Pouvoirs** | Accès complet (création de clés API, onboarding, etc.) | Accès limité (webhooks principalement) |
| **Expiration** | Non expirable | Peut expirer |
| **Révocation** | Régénération (invalide l'ancienne) | Révoquable individuellement |
| **Restrictions** | Aucune (sauf app_id) | IP, application, rate limit |
| **Usage principal** | Gestion de l'application et onboarding | Webhooks et intégrations externes |
| **Header** | `X-Master-Key` | `X-API-Key` ou `Authorization: Bearer` |

---

## 🔐 Bonnes Pratiques de Sécurité

### Pour la MasterKey
1. ✅ **Stockez-la en sécurité** : Utilisez un gestionnaire de secrets (AWS Secrets Manager, HashiCorp Vault, etc.)
2. ✅ **Ne la commitez jamais** : Ne la mettez jamais dans votre code source ou Git
3. ✅ **Utilisez des variables d'environnement** : Stockez-la dans `.env` (non versionné)
4. ✅ **Limitez l'accès** : Seules les personnes autorisées doivent y avoir accès
5. ✅ **Régénérez-la si compromise** : Si vous suspectez une fuite, régénérez-la immédiatement

### Pour les ApiKeys
1. ✅ **Créez des clés spécifiques** : Une clé par environnement (dev, staging, prod)
2. ✅ **Utilisez des noms descriptifs** : "Production Webhook Key", "Staging Integration Key"
3. ✅ **Définissez des expirations** : Pour les clés temporaires
4. ✅ **Restreignez par IP** : Limitez l'utilisation aux IPs de vos serveurs
5. ✅ **Révoquez les clés inutilisées** : Supprimez les clés qui ne sont plus nécessaires
6. ✅ **Surveillez l'utilisation** : Vérifiez régulièrement les logs d'utilisation

---

## 📝 Exemples d'Utilisation

### Exemple 1 : Utiliser MasterKey pour démarrer un onboarding
```bash
curl -X POST https://process-onboarding-main-v6bvar.laravel.cloud/api/v1/onboarding/start \
  -H "X-Master-Key: mk_live_xyz789..." \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@example.com",
    "organization_name": "Mon Entreprise"
  }'
```

### Exemple 2 : Utiliser ApiKey pour enregistrer un webhook
```bash
curl -X POST https://process-onboarding-main-v6bvar.laravel.cloud/api/webhooks/register \
  -H "X-API-Key: ak_live_abc123..." \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://monapp.com/webhooks/onboarding",
    "events": ["onboarding.completed", "onboarding.failed"]
  }'
```

### Exemple 3 : Utiliser ApiKey avec Authorization Bearer
```bash
curl -X GET https://process-onboarding-main-v6bvar.laravel.cloud/api/webhooks \
  -H "Authorization: Bearer ak_live_abc123..."
```

---

## ❓ FAQ

### Q : Puis-je utiliser ApiKey pour les endpoints d'onboarding ?
**R** : Non. Les endpoints d'onboarding nécessitent la MasterKey car ils nécessitent des privilèges élevés.

### Q : Que se passe-t-il si je perds ma MasterKey ?
**R** : Utilisez `/api/v1/applications/regenerate-master-key` avec votre `app_name` et `contact_email`. L'ancienne clé sera immédiatement invalidée.

### Q : Puis-je avoir plusieurs MasterKeys ?
**R** : Non, une seule MasterKey par application. Mais vous pouvez créer plusieurs ApiKeys.

### Q : Les ApiKeys peuvent-elles créer d'autres ApiKeys ?
**R** : Non, seule la MasterKey peut créer et gérer des ApiKeys.

### Q : Quelle clé dois-je utiliser pour les webhooks ?
**R** : Utilisez une ApiKey. C'est plus sécurisé car vous pouvez la révoquer individuellement sans affecter votre MasterKey.

### Q : Les clés sont-elles sensibles à la casse ?
**R** : Oui, les clés sont sensibles à la casse. Assurez-vous de les copier exactement.

---

## 🔗 Ressources

- **Documentation Swagger** : `https://process-onboarding-main-v6bvar.laravel.cloud/api/documentation`
- **Guide d'intégration** : `GUIDE_INTEGRATION_ONBOARDING_STATELESS.md`
- **Spécification API** : `API_SPECIFICATION.md`
