# Utilité des Informations Retournées par l'API

Ce document explique comment chaque information retournée par l'API d'onboarding aide l'application cliente à créer et gérer son tenant.

## 1. Informations de Base

### `uuid`
**Utilité** : Identifiant unique de l'onboarding

**Comment l'utiliser** :
```php
// Stocker l'UUID pour suivre l'onboarding
```php
// Stocker l'UUID pour suivre l'onboarding
$tenant = Tenant::create([
    'onboarding_uuid' => $response['uuid'], // Lier le tenant à l'onboarding
    'subdomain' => $response['subdomain'],
]);

// Utiliser l'UUID pour vérifier le statut plus tard
$status = $this->checkOnboardingStatus($response['uuid']);
```

**Cas d'usage** :
- Lier le tenant créé à l'onboarding dans votre base de données
- Vérifier le statut de l'onboarding via `/status/{uuid}`
- Corréler les logs entre votre app et le microservice
- Retry en cas d'échec (idempotence)

---

### `subdomain`
**Utilité** : Nom du sous-domaine généré (ex: `clinique-du-lac`)

**Comment l'utiliser** :
```php
// Utiliser le sous-domaine pour créer votre tenant
$tenant = Tenant::create([
    'subdomain' => $response['subdomain'], // "clinique-du-lac"
    'name' => $response['organization_name'],
]);

// Configurer le routage dans votre application
Route::domain($response['subdomain'] . '.votredomaine.com')
    ->group(function () {
        // Routes spécifiques au tenant
    });
```

**Cas d'usage** :
- Créer le tenant avec le bon sous-domaine
- Configurer le routage multi-tenant
- Générer des URLs spécifiques au tenant
- Identifier le tenant dans les requêtes

---

### `full_domain`
**Utilité** : Domaine complet (ex: `clinique-du-lac.akasigroup.local`)

**Comment l'utiliser** :
```php
// Stocker le domaine complet pour référence
$tenant = Tenant::create([
    'subdomain' => $response['subdomain'],
    'domain' => $response['full_domain'], // "clinique-du-lac.akasigroup.local"
    'url' => $response['url'],
]);

// Utiliser pour la configuration DNS côté client (si nécessaire)
$this->configureClientDns($response['full_domain']);
```

**Cas d'usage** :
- Configuration DNS supplémentaire si nécessaire
- Documentation pour l'utilisateur final
- Référence pour les emails de bienvenue
- Logs et monitoring

---

### `url`
**Utilité** : URL complète avec protocole (ex: `https://clinique-du-lac.akasigroup.local`)

**Comment l'utiliser** :
```php
// Rediriger l'utilisateur vers son tenant
return redirect($response['url']);

// Envoyer l'URL dans l'email de bienvenue
Mail::send('emails.welcome', [
    'tenant_url' => $response['url'],
    'organization_name' => $response['organization_name'],
], function ($message) use ($response) {
    $message->to($response['email'])
        ->subject('Votre espace est prêt !');
});

// Stocker pour référence
$tenant = Tenant::create([
    'url' => $response['url'],
]);
```

**Cas d'usage** :
- Redirection après création du tenant
- Envoi d'emails avec le lien d'accès
- Affichage dans l'interface admin
- Documentation utilisateur

---

### `email`
**Utilité** : Email de l'administrateur du futur tenant

**Comment l'utiliser** :
```php
// Créer l'utilisateur admin dans votre tenant
$admin = User::create([
    'email' => $response['email'], // "admin@example.com"
    'name' => $response['organization_name'],
    'role' => 'admin',
    'tenant_id' => $tenant->id,
]);

// Envoyer l'email d'activation
$this->sendActivationEmail($response['email'], $tenant);
```

**Cas d'usage** :
- Créer l'utilisateur admin dans votre système
- Envoyer les emails d'activation/bienvenue
- Lier le tenant à l'utilisateur
- Notifications et communications

---

### `organization_name`
**Utilité** : Nom de l'organisation (généré si non fourni)

**Comment l'utiliser** :
```php
// Créer le tenant avec le nom de l'organisation
$tenant = Tenant::create([
    'name' => $response['organization_name'], // "Clinique du Lac"
    'subdomain' => $response['subdomain'],
]);

// Personnaliser l'interface
$tenant->settings()->create([
    'organization_name' => $response['organization_name'],
]);
```

**Cas d'usage** :
- Nommer le tenant
- Personnaliser l'interface utilisateur
- Emails et communications
- Documentation et facturation

---

## 2. Statut et État

### `onboarding_status`
**Utilité** : Statut technique de l'onboarding (`pending`, `activated`, `cancelled`)

**Comment l'utiliser** :
```php
// Vérifier que l'onboarding est prêt avant de créer le tenant
if ($response['onboarding_status'] === 'activated') {
    // L'infrastructure est prête, on peut créer le tenant
    $this->createTenant($response);
} elseif ($response['onboarding_status'] === 'pending') {
    // Attendre et vérifier plus tard
    $this->scheduleStatusCheck($response['uuid']);
} else {
    // Onboarding annulé, gérer l'erreur
    $this->handleCancelledOnboarding($response);
}
```

**Cas d'usage** :
- Décider quand créer le tenant
- Gérer les workflows asynchrones
- Afficher le statut à l'utilisateur
- Gérer les erreurs et annulations

---

## 3. Metadata Techniques

### `metadata.dns_configured`
**Utilité** : Indique si le DNS est configuré (`true`/`false`)

**Comment l'utiliser** :
```php
// Vérifier que le DNS est prêt avant de créer le tenant
if ($response['metadata']['dns_configured']) {
    // DNS configuré, le sous-domaine est accessible
    $this->createTenant($response);
} else {
    // Attendre la configuration DNS
    $this->waitForDns($response['uuid']);
}
```

**Cas d'usage** :
- Vérifier la disponibilité du sous-domaine
- Décider quand créer le tenant
- Gérer les workflows asynchrones
- Monitoring et alertes

---

### `metadata.ssl_configured`
**Utilité** : Indique si le SSL est configuré (`true`/`false`)

**Comment l'utiliser** :
```php
// Vérifier que HTTPS est disponible
if ($response['metadata']['ssl_configured']) {
    // SSL configuré, utiliser HTTPS
    $url = str_replace('http://', 'https://', $response['url']);
    $this->redirectToTenant($url);
} else {
    // SSL pas encore configuré, utiliser HTTP temporairement
    $this->redirectToTenant($response['url']);
}
```

**Cas d'usage** :
- Décider d'utiliser HTTP ou HTTPS
- Sécurité et conformité
- Redirections sécurisées
- Monitoring SSL

---

### `metadata.infrastructure_status`
**Utilité** : Statut global de l'infrastructure (`pending`, `partial`, `ready`)

**Comment l'utiliser** :
```php
switch ($response['metadata']['infrastructure_status']) {
    case 'ready':
        // DNS + SSL configurés, tout est prêt
        $this->createTenant($response);
        break;
        
    case 'partial':
        // Un seul élément configuré, attendre
        $this->scheduleRetry($response['uuid']);
        break;
        
    case 'pending':
        // Rien n'est configuré, attendre
        $this->waitForProvisioning($response['uuid']);
        break;
}
```

**Cas d'usage** :
- Décision rapide sur l'état de l'infrastructure
- Workflow conditionnel
- Affichage du statut à l'utilisateur
- Retry automatique

---

### `metadata.provisioning_attempts`
**Utilité** : Nombre de tentatives de provisioning

**Comment l'utiliser** :
```php
// Détecter les problèmes de provisioning
if ($response['metadata']['provisioning_attempts'] > 3) {
    // Trop de tentatives, alerter l'admin
    $this->alertAdmin('Problème de provisioning', $response);
}

// Limiter les retries côté client
if ($response['metadata']['provisioning_attempts'] < 5) {
    $this->retryProvisioning($response['uuid']);
}
```

**Cas d'usage** :
- Détecter les problèmes récurrents
- Limiter les retries
- Monitoring et alertes
- Debugging

---

### `metadata.is_idempotent`
**Utilité** : Indique si l'appel `/provision` était idempotent

**Comment l'utiliser** :
```php
// Savoir si c'était un re-provisioning
if ($response['metadata']['is_idempotent']) {
    // C'était un appel répété, pas de nouvelle configuration
    Log::info('Provisioning idempotent', ['uuid' => $response['uuid']]);
} else {
    // Première fois, nouvelle configuration
    $this->createTenant($response);
}
```

**Cas d'usage** :
- Éviter les actions dupliquées
- Logging et debugging
- Comprendre le comportement de l'API
- Gestion des retries

---

## 4. Clés API (si générées)

### `api_key` et `api_secret`
**Utilité** : Clés API pour l'application cliente (pas pour le tenant final)

**Comment l'utiliser** :
```php
// Stocker les clés API de manière sécurisée
if ($response['api_key'] && $response['api_secret']) {
    // Stocker dans un vault ou base chiffrée
    $this->secureStorage->store([
        'onboarding_uuid' => $response['uuid'],
        'api_key' => $response['api_key'],
        'api_secret' => $response['api_secret'],
    ]);
    
    // Utiliser pour appeler d'autres APIs du microservice
    $this->configureApiClient($response['api_key'], $response['api_secret']);
}
```

**Cas d'usage** :
- Authentification pour d'autres endpoints
- Appels API ultérieurs au microservice
- Intégration avec d'autres services
- **Note** : Ces clés sont pour l'app cliente, pas pour le tenant final

---

## 5. Exemple de Workflow Complet

```php
class TenantOnboardingService
{
    public function handleOnboardingResponse(array $response)
    {
        // 1. Vérifier que l'infrastructure est prête
        if ($response['metadata']['infrastructure_status'] !== 'ready') {
            // Programmer une vérification plus tard
            $this->scheduleStatusCheck($response['uuid']);
            return;
        }
        
        // 2. Créer le tenant avec les informations reçues
        $tenant = Tenant::create([
            'onboarding_uuid' => $response['uuid'],
            'subdomain' => $response['subdomain'],
            'domain' => $response['full_domain'],
            'url' => $response['url'],
            'name' => $response['organization_name'],
            'status' => 'active',
        ]);
        
        // 3. Créer l'utilisateur admin
        $admin = User::create([
            'tenant_id' => $tenant->id,
            'email' => $response['email'],
            'name' => $response['organization_name'],
            'role' => 'admin',
        ]);
        
        // 4. Stocker les clés API si fournies
        if ($response['api_key']) {
            $this->storeApiKeys($tenant, $response['api_key'], $response['api_secret']);
        }
        
        // 5. Envoyer l'email de bienvenue avec l'URL
        Mail::send('emails.tenant-ready', [
            'tenant_url' => $response['url'],
            'organization_name' => $response['organization_name'],
        ], function ($message) use ($response) {
            $message->to($response['email'])
                ->subject('Votre espace est prêt !');
        });
        
        // 6. Rediriger vers le tenant
        return redirect($response['url']);
    }
}
```

---

## Résumé : Utilité de Chaque Champ

| Champ | Utilité Principale | Action de l'App Cliente |
|-------|-------------------|-------------------------|
| `uuid` | Identifier l'onboarding | Stocker, lier au tenant, vérifier le statut |
| `subdomain` | Nom du sous-domaine | Créer le tenant, configurer le routage |
| `full_domain` | Domaine complet | Référence, documentation, DNS |
| `url` | URL complète | Redirection, emails, affichage |
| `email` | Email admin | Créer l'utilisateur, envoyer emails |
| `organization_name` | Nom organisation | Nommer le tenant, personnalisation |
| `onboarding_status` | État technique | Décider quand créer le tenant |
| `metadata.dns_configured` | DNS prêt ? | Vérifier disponibilité sous-domaine |
| `metadata.ssl_configured` | SSL prêt ? | Utiliser HTTPS/HTTP |
| `metadata.infrastructure_status` | État global | Workflow conditionnel |
| `metadata.provisioning_attempts` | Nombre tentatives | Détecter problèmes, limiter retries |
| `metadata.is_idempotent` | Appel répété ? | Éviter actions dupliquées |
| `api_key` / `api_secret` | Clés API app cliente | Authentification autres endpoints |

---

## Bonnes Pratiques

1. **Toujours vérifier `infrastructure_status`** avant de créer le tenant
2. **Stocker l'UUID** pour corréler avec les logs du microservice
3. **Utiliser `url`** pour les redirections et emails
4. **Gérer les cas `pending`** avec des vérifications périodiques
5. **Sécuriser les clés API** dans un vault ou base chiffrée
6. **Logger tous les champs** pour le debugging (sauf secrets)
