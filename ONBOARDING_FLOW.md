# Flux d'Onboarding - Akasi Group

## Vue d'ensemble

Le processus d'onboarding utilise deux tables principales pour gérer le cycle de vie d'un tenant :

1. **`onboarding_sessions`** : Stocke les métadonnées de la session d'onboarding
2. **`onboarding_activations`** : Stocke les tokens d'activation pour finaliser l'inscription

## Flux complet

### Étape 1 : Collecte initiale
- L'utilisateur remplit le formulaire avec :
  - Email
  - Nom de l'organisation
  - reCAPTCHA
- Les données sont stockées en session PHP

### Étape 2 : Traitement asynchrone (API `/api/onboarding/process`)
1. **Création du tenant** :
   - Génération d'un slug unique
   - Génération d'un sous-domaine unique
   - Création de la base de données (`akasigroup_{subdomain}`)
   - Création du sous-domaine

2. **Enregistrement dans `onboarding_sessions`** :
   ```php
   - session_id
   - hospital_name (nom de l'organisation)
   - slug
   - admin_email (email de l'utilisateur)
   - subdomain
   - database_name
   - status = 'pending_activation' (⚠️ IMPORTANT : pas encore activé)
   - completed_at = null
   ```

3. **Création de la base du tenant** :
   - Exécution des migrations dans la base du tenant
   - Initialisation des settings par défaut
   - ⚠️ **AUCUN UTILISATEUR N'EST CRÉÉ À CE STADE**

4. **Création du token d'activation dans `onboarding_activations`** :
   ```php
   - email
   - organization_name
   - token (64 caractères aléatoires)
   - subdomain
   - database_name
   - expires_at (24h après création)
   - activated_at = null (sera rempli lors de l'activation)
   ```

5. **Envoi de l'email d'activation** :
   - Email contenant le lien : `/onboarding/activate/{token}?email={email}`
   - Le token est valide pendant 24 heures

### Étape 3 : Activation du compte
Lorsque l'utilisateur clique sur le lien dans l'email :

1. **Vérification du token** :
   - Le token est recherché dans `onboarding_activations`
   - Vérification que le token n'est pas expiré
   - Vérification que le token n'a pas déjà été utilisé

2. **Création de l'utilisateur** :
   - Basculement vers la base de données du tenant
   - **Création de l'utilisateur admin dans la base du tenant** :
     ```php
     - name = organization_name
     - email
     - password (hashé)
     - role = 'admin'
     - status = 'active'
     - email_verified_at = now()
     ```

3. **Mise à jour des tables** :
   - `onboarding_activations.activated_at` = maintenant
   - `onboarding_sessions.status` = 'completed' (optionnel)
   - `onboarding_sessions.completed_at` = maintenant (optionnel)

4. **Connexion automatique** :
   - L'utilisateur est automatiquement connecté
   - Redirection vers le dashboard du tenant

## Utilisation des tables

### `onboarding_sessions`
**Rôle** : Métadonnées de la session d'onboarding
- Permet de retrouver un tenant par sous-domaine
- Stocke l'état de l'onboarding (`pending_activation`, `completed`, `failed`)
- Utilisé par `TenantService` pour trouver la base de données d'un tenant

**Champs importants** :
- `subdomain` : Identifiant unique du tenant
- `database_name` : Nom de la base de données du tenant
- `admin_email` : Email de l'administrateur
- `status` : État de l'onboarding

### `onboarding_activations`
**Rôle** : Gestion des tokens d'activation
- Stocke les tokens uniques pour activer les comptes
- Permet de vérifier la validité d'un lien d'activation
- Empêche la réutilisation d'un token déjà utilisé

**Champs importants** :
- `token` : Token unique (64 caractères)
- `email` : Email de l'utilisateur à activer
- `subdomain` : Sous-domaine du tenant
- `database_name` : Base de données du tenant
- `expires_at` : Date d'expiration (24h)
- `activated_at` : Date d'activation (null si pas encore activé)

## Points importants

1. **L'utilisateur n'est PAS créé lors de la création du tenant**
   - Le tenant est créé avec une base de données vide (sauf les tables de structure)
   - L'utilisateur est créé uniquement lors de l'activation (étape 3)

2. **Le token d'activation est unique et sécurisé**
   - 64 caractères aléatoires
   - Expire après 24 heures
   - Ne peut être utilisé qu'une seule fois

3. **Deux tables pour deux objectifs différents**
   - `onboarding_sessions` : Métadonnées et suivi
   - `onboarding_activations` : Sécurité et activation

## Dépannage

### Email non envoyé
- Vérifier la configuration mail dans `.env`
- Vérifier les logs : `storage/logs/laravel.log`
- Le token est créé même si l'email échoue

### Utilisateur non créé
- L'utilisateur est créé uniquement lors de l'activation
- Vérifier que l'utilisateur a cliqué sur le lien d'activation
- Vérifier que le token n'est pas expiré

### Token invalide
- Vérifier que le token existe dans `onboarding_activations`
- Vérifier que `expires_at` n'est pas dépassé
- Vérifier que `activated_at` est null
