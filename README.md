# Microservice Onboarding - Service d'Infrastructure et d'Enregistrement

Ce microservice est un **service d'infrastructure et d'enregistrement universel** qui permet à n'importe quelle application de gérer l'onboarding de ses clients de manière autonome. Le microservice fournit l'infrastructure (bases de données, sous-domaines, DNS, SSL) tandis que chaque application gère ses propres tenants.

## 🚀 Démarrage Rapide

**Nouveau développeur ?** Consultez le **[Guide de Démarrage Rapide](GUIDE_DEMARRAGE_RAPIDE.md)** pour réutiliser ce microservice dans votre projet en **moins de 5 minutes** !

> **Note** : Ce microservice est entièrement configurable via les variables d'environnement. Consultez le [Guide de Personnalisation](GUIDE_PERSONNALISATION.md) pour personnaliser le branding et les traductions.

## 🚀 Fonctionnalités Clés

### Service d'Infrastructure
- **Enregistrement Self-Service** : Les applications peuvent s'enregistrer elles-mêmes via l'API
- **Création de Bases de Données** : Le microservice crée et gère une base de données MySQL pour chaque application
- **Génération de Sous-domaines** : Génération automatique de sous-domaines uniques
- **Configuration DNS/SSL** : Configuration automatique de l'infrastructure réseau
- **Génération de Clés API** : Génération optionnelle de clés API pour les requêtes spécifiques

### API RESTful Stateless
- **API Sans État** : Pas de sessions, chaque requête est indépendante
- **Authentification Flexible** : Support master_key et clés API
- **Validation Dynamique** : Règles de validation configurables par clé API
- **Génération Automatique** : Génération automatique de données manquantes (organization_name, etc.)

### Gestion Multi-Application
- **Isolation par Application** : Chaque application a sa propre base de données
- **Self-Service** : Les applications gèrent leurs propres clés API
- **Flexibilité Maximale** : Configuration personnalisable par application

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

### Enregistrement d'Application (Self-Service)
```
POST /api/v1/applications/register
→ Crée l'application (sans base de données)
→ Retourne : app_id, master_key
→ Note : Seule la master key est nécessaire pour démarrer un onboarding
```

### Onboarding (Avec master_key)
```
POST /api/v1/onboarding/register
Headers: X-Master-Key: {master_key}
→ Enregistre un onboarding
→ Génère sous-domaine, configure DNS/SSL
→ Retourne : uuid, subdomain, api_key (si généré)
```

### Authentification
- **Master Key** : Pour gérer les clés API et les onboardings
- **API Key** : Pour les requêtes spécifiques (si générée)

### 📖 Documentation Interactive (Swagger)
Une documentation interactive complète et testable est disponible :
- **Adresse** : `/api/documentation`
- **Lien local** : [http://localhost:8000/api/documentation](http://localhost:8000/api/documentation)

Cette interface permet de tester tous les endpoints en saisissant vos headers `X-API-Key` et `X-App-Name` via le bouton **Authorize**.

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

### Branding et Configuration

Le microservice est entièrement configurable via les variables d'environnement :

- **Branding** : Nom de la marque, domaine, préfixe de base de données
- **Emails** : Nom et adresse de l'expéditeur
- **Traductions** : Support multi-langues (français et anglais inclus)

Consultez le [Guide de Personnalisation](GUIDE_PERSONNALISATION.md) pour plus de détails.

### Personnalisation par Tenant

Le système de "Branding" permet aux tenants de modifier :
- **Couleurs** : Primaire, secondaire, accent et fond.
- **Interface** : Logo personnalisé et message de bienvenue.
- **Navigation** : Réorganisation et renommage des menus de la sidebar.

## 🛡 Sécurité

- Isolation stricte des bases de données.
- Protection contre les attaques par force brute sur l'activation.
- Validation reCAPTCHA sur les formulaires publics.
- Tokens d'auto-login à usage unique et courte durée.

## 📚 Documentation unique pour intégration

Pour toute intégration de ce microservice dans une application externe, référez-vous **uniquement** à :

- **[Guide d’Intégration Onboarding Stateless](GUIDE_INTEGRATION_ONBOARDING_STATELESS.md)**  
  Ce document explique :
  - le rôle du microservice,
  - les endpoints à utiliser,
  - le flux complet (`start`, `provision`, `status`),
  - les responsabilités côté microservice vs côté application cliente,
  - les exemples de requêtes/réponses et les bonnes pratiques.

> Les autres fichiers `.md` présents dans le dépôt sont à considérer comme documents internes ou historiques. Pour les équipes externes, le point d’entrée unique est **GUIDE_INTEGRATION_ONBOARDING_STATELESS.md**.
