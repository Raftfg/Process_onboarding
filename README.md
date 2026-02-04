# Akasi Onboarding Microservice

Ce microservice est une solution d'onboarding **SaaS Multi-tenant** robuste et réutilisable, conçue pour gérer la création dynamique d'espaces clients (tenants) avec isolation complète des données (une base de données par client).

## 🚀 Fonctionnalités Clés

- **Multi-tenancy Dynamique** : Isolation totale via des bases de données séparées.
- **Support Multi-App (Secteur)** : Plusieurs applications peuvent utiliser l'API simultanément avec isolation des noms d'organisation par `X-App-Name`.
- **Gestion de Sous-domaines** : Chaque tenant accède à son propre espace via `client.votre-domaine.com`.
- **Flux d'Onboarding Complet** : 
  - Formulaire d'inscription avec validation reCAPTCHA.
  - Système d'activation par email sécurisé (tokens à usage unique).
  - Provisioning automatique de la base de données et des tables nécessaires.
- **Onboarding Externe & Migrations** : Capacité à injecter des migrations SQL personnalisées lors de la création d'un tenant via l'API.
- **Tableau de Bord Administrateur (Super Admin)** : Pour gérer les tenants, surveiller l'activité et générer des clés API.
- **API Publique** : Permet l'intégration de l'onboarding dans d'autres applications.
- **Système de Webhooks** : Notifications en temps réel (avec signature HMAC) lors des événements d'onboarding.
- **Personnalisation (White-label)** : Les clients peuvent personnaliser leur logo, leurs couleurs et leur menu depuis leur propre dashboard.
- **Design Minimaliste** : Interface moderne, épurée et sans surcharge visuelle.

## 🛠 Prérequis

- PHP 8.1+
- MySQL 8.0+
- Serveur Web (Apache/Nginx) supportant les Wildcard Subdomains
- Composer

## 📦 Installation

1. **Clonage et Dépendances**
   ```bash
   git clone [url-du-repo]
   composer install
   ```

2. **Configuration**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   *Note : Configurez vos accès MySQL dans le `.env`. L'utilisateur MySQL doit avoir les droits `CREATE DATABASE`.*

3. **Base de Données Centrale**
   ```bash
   php artisan migrate --seed
   ```
   *Le seeder crée l'administrateur par défaut : `admin@akasi.com` / `password`.*

4. **Lien de Stockage**
   ```bash
   php artisan storage:link
   ```

## ⚙️ Configuration Spécifique

### Domaines et Sessions
Pour que l'authentification fonctionne sur les sous-domaines, configurez :
- `SESSION_DOMAIN=.votre-domaine.com` (Notez le point au début).
- En développement local avec `127.0.0.1`, laissez `SESSION_DOMAIN` vide (le système s'adaptera dynamiquement).

### Wildcard Subdomains
Assurez-vous que votre serveur web ou votre DNS redirige `*.votre-domaine.com` vers le répertoire `public` du projet.

## 🔌 API Publique

### Authentification API
Toutes les requêtes API doivent inclure les headers :
- `X-API-Key: votre_cle_api`
- `X-App-Name: nom_de_votre_app` (Requis pour l'isolation)

*(Générez vos clés et configurez vos apps dans le Dashboard Super Admin)*

### Endpoints Principaux

| Méthode | Endpoint | Description |
| :--- | :--- | :--- |
| `GET` | `/api/onboarding/status/{subdomain}` | Récupère le statut d'un tenant |
| `POST` | `/api/v1/onboarding/external` | Onboarding via App externe (Multi-App) |
| `POST` | `/api/webhooks/register` | Enregistre une URL de webhook |

### Exemple de création d'onboarding
```json
{
  "organization": {
    "name": "Hôpital Central",
    "email": "contact@hopital.com"
  },
  "admin": {
    "first_name": "Jean",
    "last_name": "Dupont",
    "email": "admin@hopital.com"
  }
}
```

## 🪝 Webhooks

Le service envoie un JSON signé vers vos URLs enregistrées lors de la complétion d'un onboarding.
**Vérification de signature** : Le header `X-Akasi-Signature` contient le hash HMAC SHA256 du body, calculé avec votre `WEBHOOK_SECRET`.

## 🎨 Personnalisation

Le système de "Branding" permet de modifier :
- **Couleurs** : Primaire, secondaire, accent et fond.
- **Interface** : Logo personnalisé et message de bienvenue.
- **Navigation** : Réorganisation et renommage des menus de la sidebar.

## 🛡 Sécurité

- Isolation stricte des bases de données.
- Protection contre les attaques par force brute sur l'activation.
- Validation reCAPTCHA sur les formulaires publics.
- Tokens d'auto-login à usage unique et courte durée.

---
© 2026 Akasi Group. Tous droits réservés.
