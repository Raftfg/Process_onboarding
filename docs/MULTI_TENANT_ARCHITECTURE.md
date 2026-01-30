# Architecture Multi-Tenant - Documentation

## 📋 Vue d'ensemble

Ce document décrit l'architecture multi-tenant implémentée dans le système d'onboarding MedKey. Chaque client (tenant) possède sa propre base de données isolée avec ses propres utilisateurs et données.

## 🏗️ Architecture

### Base de données principale

La base principale (`onboarding`) contient :

#### Table `tenants`
- Stocke les informations de chaque client/tenant
- Champs : `id`, `subdomain`, `database_name`, `name`, `email`, `phone`, `address`, `status`, `plan`, `created_at`, `updated_at`, `deleted_at`
- Statuts possibles : `active`, `suspended`, `inactive`

### Bases de données par tenant

Chaque tenant possède sa propre base de données avec les tables suivantes :

#### Table `users`
- Utilisateurs du tenant
- Champs : `id`, `name`, `email`, `password`, `role`, `email_verified_at`, `remember_token`, `created_at`, `updated_at`
- Rôles : `admin`, `user`, `manager`

#### Table `information_personnes`
- Informations détaillées des personnes
- Champs : `id`, `user_id`, `prenom`, `nom`, `date_naissance`, `sexe`, `telephone`, `adresse`, `ville`, `code_postal`, `pays`, `photo`, `notes`, `created_at`, `updated_at`

#### Table `configuration_dashboard`
- Configuration du dashboard par utilisateur
- Champs : `id`, `user_id`, `theme`, `langue`, `widgets_config` (JSON), `preferences` (JSON), `created_at`, `updated_at`

#### Table `sessions`
- Sessions Laravel pour le tenant

## 🔄 Flux d'authentification

1. **Détection du tenant** : Le middleware `DetectTenant` extrait le sous-domaine depuis l'URL
2. **Vérification** : Vérifie que le tenant existe et est actif dans la base principale
3. **Switch de base** : Bascule la connexion DB vers la base du tenant
4. **Configuration Auth** : Configure le modèle d'authentification pour utiliser `Tenant\User`
5. **Authentification** : `TenantAuthService` authentifie l'utilisateur dans la base du tenant

## 📁 Structure des fichiers

```
app/
├── Models/
│   ├── Tenant.php (base principale)
│   └── Tenant/
│       ├── User.php
│       ├── InformationPersonne.php
│       └── ConfigurationDashboard.php
├── Services/
│   ├── TenantService.php (gestion des tenants)
│   └── TenantAuthService.php (authentification tenant-aware)
└── Http/
    ├── Controllers/
    │   ├── Auth/
    │   │   ├── LoginController.php
    │   │   └── LogoutController.php
    │   └── DashboardController.php
    └── Middleware/
        └── DetectTenant.php

database/
└── migrations/
    ├── 2026_01_30_075301_create_tenants_table.php (base principale)
    └── tenant/
        ├── 2024_01_01_000000_create_users_table.php
        ├── 2024_01_01_000001_create_information_personnes_table.php
        ├── 2024_01_01_000002_create_configuration_dashboard_table.php
        └── 2024_01_01_000003_create_sessions_table.php
```

## 🔧 Services

### TenantService

Gère les opérations sur les tenants :

- `getTenantDatabase($subdomain)` : Récupère le nom de la base de données
- `switchToTenantDatabase($databaseName)` : Bascule vers la base du tenant
- `getTenantBySubdomain($subdomain)` : Récupère un tenant
- `createTenant($data)` : Crée un nouveau tenant
- `getAllTenants($filters)` : Liste tous les tenants
- `updateTenantStatus($subdomain, $status)` : Met à jour le statut
- `deleteTenant($subdomain)` : Supprime un tenant (soft delete)
- `runTenantMigrations($databaseName)` : Exécute les migrations tenant

### TenantAuthService

Gère l'authentification tenant-aware :

- `authenticate($email, $password, $subdomain, $remember)` : Authentifie un utilisateur
- `getCurrentTenant()` : Récupère le tenant actuel
- `isAuthenticated()` : Vérifie si un utilisateur est authentifié
- `logout()` : Déconnecte et revient à la base principale

## 🛡️ Middleware

### DetectTenant

- S'exécute sur toutes les requêtes web
- Extrait le sous-domaine depuis l'URL
- Vérifie l'existence et le statut du tenant
- Bascule automatiquement la connexion DB
- Configure le modèle d'authentification

## 📝 Processus d'onboarding

1. L'utilisateur remplit le formulaire d'onboarding
2. `OnboardingService` :
   - Génère un sous-domaine unique
   - Crée la base de données du tenant
   - Crée l'entrée dans la table `tenants` (base principale)
   - Bascule vers la base du tenant
   - Exécute les migrations tenant
   - Crée l'utilisateur administrateur
   - Revient à la base principale
3. L'utilisateur est redirigé vers son sous-domaine

## 🔐 Sécurité

- Isolation complète des données entre tenants
- Vérification du statut du tenant avant authentification
- Validation du sous-domaine
- Logs d'authentification par tenant
- Soft delete pour les tenants

## 🚀 Utilisation

### Créer un tenant manuellement

```php
$tenantService = app(TenantService::class);

$tenant = $tenantService->createTenant([
    'subdomain' => 'mon-tenant',
    'database_name' => 'medkey_mon-tenant',
    'name' => 'Mon Organisation',
    'email' => 'contact@organisation.com',
    'status' => 'active',
]);
```

### Authentifier un utilisateur

```php
$authService = app(TenantAuthService::class);

try {
    $user = $authService->authenticate(
        'user@example.com',
        'password',
        'mon-tenant'
    );
} catch (\Illuminate\Validation\ValidationException $e) {
    // Gérer les erreurs
}
```

### Accéder aux données du tenant

Une fois le middleware `DetectTenant` exécuté, toutes les requêtes utilisent automatiquement la base du tenant :

```php
// Utilise automatiquement la base du tenant
$users = \App\Models\Tenant\User::all();
$personnes = \App\Models\Tenant\InformationPersonne::all();
```

## 📊 Migration des données existantes

Si vous avez des données existantes dans `OnboardingSession`, vous pouvez les migrer vers `Tenant` :

```php
$sessions = OnboardingSession::where('status', 'completed')->get();

foreach ($sessions as $session) {
    Tenant::firstOrCreate(
        ['subdomain' => $session->subdomain],
        [
            'database_name' => $session->database_name,
            'name' => $session->hospital_name,
            'email' => $session->hospital_email ?? $session->admin_email,
            'phone' => $session->hospital_phone,
            'address' => $session->hospital_address,
            'status' => 'active',
        ]
    );
}
```

## ⚠️ Notes importantes

1. **Isolation** : Les données sont complètement isolées entre tenants
2. **Performance** : Le cache est utilisé pour améliorer les performances
3. **Sessions** : Chaque tenant a ses propres sessions
4. **Migrations** : Les migrations tenant sont exécutées automatiquement lors de la création
5. **Rollback** : En cas d'erreur, le système revient automatiquement à la base principale

## 🔍 Dépannage

### Le tenant n'est pas détecté

- Vérifiez que le sous-domaine est correct dans l'URL
- Vérifiez que le tenant existe dans la table `tenants`
- Vérifiez que le statut est `active`

### Erreur d'authentification

- Vérifiez que la base de données du tenant existe
- Vérifiez que les migrations ont été exécutées
- Vérifiez que l'utilisateur existe dans la base du tenant

### Erreur de connexion DB

- Vérifiez les credentials MySQL
- Vérifiez que la base de données existe
- Vérifiez les logs Laravel

