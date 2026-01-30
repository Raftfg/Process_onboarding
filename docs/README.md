# MedKey - Système d'Onboarding Multi-Tenant

Système d'onboarding réutilisable pour MedKey permettant de créer automatiquement des sous-domaines et bases de données pour chaque nouvel hôpital.

## 🎯 Intégration dans votre projet

**Vous voulez utiliser ce microservice dans votre projet ?** 

👉 Consultez le **[Guide d'Intégration complet](INTEGRATION.md)** qui explique comment :
- Intégrer via API REST (sans installation)
- Utiliser les exemples de code (JavaScript, PHP, React, Vue.js)
- Configurer les webhooks
- Gérer l'authentification

**Démarrage rapide :**
```javascript
// Exemple JavaScript
const response = await fetch('https://onboarding.medkey.com/api/onboarding/create', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': 'Bearer YOUR_API_KEY'
  },
  body: JSON.stringify({
    hospital: { name: 'Hôpital Central', ... },
    admin: { first_name: 'Jean', ... }
  })
});
```

Voir les [exemples complets](examples/) pour plus de détails.

## 🚀 Installation

### Prérequis
- PHP >= 8.1
- Composer
- MySQL/MariaDB
- Extension PDO MySQL

### Étapes d'installation

1. **Installer les dépendances** :
```bash
composer install
```

2. **Copier le fichier `.env.example` vers `.env`** :
```bash
cp .env.example .env
```

3. **Générer la clé d'application** :
```bash
php artisan key:generate
```

4. **Configurer la base de données dans `.env`** :
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=onboarding
DB_USERNAME=root
DB_PASSWORD=votre_mot_de_passe

# Credentials root MySQL pour créer les bases de données
DB_ROOT_USERNAME=root
DB_ROOT_PASSWORD=votre_mot_de_passe_root
```

5. **Exécuter les migrations** :
```bash
php artisan migrate
```

6. **Configurer le mail (optionnel)** :
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=votre_username
MAIL_PASSWORD=votre_password
MAIL_FROM_ADDRESS="noreply@medkey.com"
MAIL_FROM_NAME="MedKey"
```

7. **Configurer Acrylic DNS Proxy (Windows uniquement, pour les sous-domaines locaux)** :
```powershell
# Exécutez en tant qu'administrateur
.\scripts\setup-acrylic.ps1
```
👉 Voir [ACRYLIC_DNS_SETUP.md](ACRYLIC_DNS_SETUP.md) pour les instructions détaillées.

8. **Démarrer le serveur de développement** :
```bash
php artisan serve --host=0.0.0.0 --port=8000
```

**Important** : Utilisez `--host=0.0.0.0` pour que le serveur écoute sur toutes les interfaces (nécessaire pour les sous-domaines).

Accédez à `http://localhost:8000` (ou `http://127.0.0.1:8000`) dans votre navigateur pour commencer l'onboarding.

**Note** : N'utilisez pas `http://0.0.0.0:8000` dans le navigateur, cette adresse est uniquement pour la configuration du serveur.

## ⚙️ Configuration

### Variables d'environnement importantes

- `SUBDOMAIN_BASE_DOMAIN` : Domaine de base pour les sous-domaines (ex: medkey.local)
- `SUBDOMAIN_WEB_ROOT` : Chemin racine web pour les sous-domaines
- `DB_ROOT_USERNAME` : Nom d'utilisateur root MySQL pour créer les bases de données
- `DB_ROOT_PASSWORD` : Mot de passe root MySQL

### Configuration des sous-domaines

**Pour le développement local sur Windows :**

Pour que les sous-domaines fonctionnent en local (ex: `http://tobi-melvin-1769757006.localhost:8000`), vous devez configurer Acrylic DNS Proxy qui supporte les wildcards DNS.

👉 **Voir le guide complet : [ACRYLIC_DNS_SETUP.md](ACRYLIC_DNS_SETUP.md)**

**Installation rapide :**
```powershell
# 1. Téléchargez Acrylic DNS Proxy depuis https://sourceforge.net/projects/acrylic/
# 2. Installez Acrylic
# 3. Exécutez le script de configuration (en tant qu'administrateur)
.\scripts\setup-acrylic.ps1
```

**Pour la production :**

Voir le fichier `SUBDOMAIN_SETUP.md` pour les instructions détaillées sur la configuration Apache/Nginx et DNS.

## ✨ Fonctionnalités

1. **Page de bienvenue** : Accueil avec bouton "Démarrer"
2. **Étape 1** : Saisie des informations de l'hôpital
   - Nom de l'hôpital (obligatoire)
   - Adresse
   - Téléphone
   - Email
3. **Étape 2** : Saisie des informations de l'administrateur
   - Prénom et nom
   - Email administrateur
   - Mot de passe (minimum 8 caractères)
4. **Traitement automatique** : 
   - Création automatique de la base de données
   - Génération du sous-domaine
   - Envoi d'email de bienvenue à l'administrateur
   - Redirection vers le sous-domaine avec message de bienvenue

## 📁 Structure du projet

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Api/
│   │   │   └── OnboardingApiController.php  # API pour le traitement
│   │   ├── OnboardingController.php        # Contrôleur pour les vues
│   │   └── WelcomeController.php          # Page de bienvenue
│   └── ...
├── Mail/
│   └── OnboardingWelcomeMail.php           # Email de bienvenue
├── Models/
│   └── OnboardingSession.php               # Modèle pour les sessions
└── Services/
    └── OnboardingService.php               # Logique métier

resources/
└── views/
    ├── layouts/
    │   └── app.blade.php                   # Layout principal
    ├── onboarding/
    │   ├── welcome.blade.php               # Page 1: Bienvenue
    │   ├── step1.blade.php                 # Page 2: Infos hôpital
    │   └── step2.blade.php                 # Page 3: Infos admin
    ├── welcome.blade.php                   # Page de bienvenue sous-domaine
    └── emails/
        └── onboarding-welcome.blade.php    # Template email

routes/
├── web.php                                 # Routes web
└── api.php                                 # Routes API
```

## 🎨 Design

Le système utilise un design moderne avec :
- Interface responsive
- Animations fluides
- Indicateur de progression
- Écran de chargement pendant le traitement
- Design gradient moderne (violet/bleu)

## 🔒 Sécurité

- Validation des données côté serveur
- Protection CSRF
- Mots de passe minimum 8 caractères avec confirmation
- Validation des emails
- Sessions sécurisées

## 📝 Notes importantes

- **Production** : Vous devrez implémenter la création réelle des vhosts Apache/Nginx (voir `SUBDOMAIN_SETUP.md`)
- **DNS** : La gestion DNS doit être configurée selon votre infrastructure
- **Base de données** : Les bases de données sont créées avec le préfixe `medkey_`
- **Sous-domaines** : Les sous-domaines sont générés à partir du nom de l'hôpital (slugifié)

## 🐛 Dépannage

### Erreur de création de base de données
- Vérifiez que `DB_ROOT_USERNAME` et `DB_ROOT_PASSWORD` sont corrects
- Assurez-vous que l'utilisateur MySQL a les droits de création de bases de données

### Email non envoyé
- Vérifiez la configuration SMTP dans `.env`
- Pour le développement, utilisez Mailtrap ou un service similaire

### Sous-domaine non accessible
- Vérifiez la configuration Apache/Nginx
- Ajoutez l'entrée dans `/etc/hosts` pour le développement local
- Voir `SUBDOMAIN_SETUP.md` pour plus de détails

## 📄 Licence

MIT
