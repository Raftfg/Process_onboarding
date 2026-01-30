# Guide d'Installation Détaillé

## Installation pas à pas

### 1. Cloner ou télécharger le projet

```bash
cd /var/www/html  # ou votre répertoire de travail
# Copiez tous les fichiers du projet ici
```

### 2. Installer Composer (si pas déjà installé)

```bash
# Linux/Mac
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Windows
# Téléchargez depuis https://getcomposer.org/download/
```

### 3. Installer les dépendances PHP

```bash
composer install
```

### 4. Configuration de l'environnement

```bash
# Copier le fichier .env.example
cp .env.example .env

# Éditer le fichier .env
nano .env  # ou votre éditeur préféré
```

Configurer les variables suivantes dans `.env` :

```env
APP_NAME=MedKey
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=onboarding
DB_USERNAME=root
DB_PASSWORD=votre_mot_de_passe

DB_ROOT_USERNAME=root
DB_ROOT_PASSWORD=votre_mot_de_passe_root

SUBDOMAIN_BASE_DOMAIN=medkey.local
SUBDOMAIN_WEB_ROOT=/var/www/html
```

### 5. Générer la clé d'application

```bash
php artisan key:generate
```

### 6. Créer la base de données principale

Connectez-vous à MySQL :

```bash
mysql -u root -p
```

Créez la base de données :

```sql
CREATE DATABASE onboarding CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;
```

### 7. Exécuter les migrations

```bash
php artisan migrate
```

Cela créera les tables nécessaires :
- `sessions` : Pour la gestion des sessions
- `onboarding_sessions` : Pour stocker les sessions d'onboarding

### 8. Configurer les permissions (Linux/Mac)

```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### 9. Configuration du serveur web

#### Apache

Assurez-vous que `mod_rewrite` est activé :

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

#### Nginx

Configurez Nginx pour pointer vers le répertoire `public` :

```nginx
server {
    listen 80;
    server_name localhost;
    root /var/www/html/public;
    
    index index.php;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 10. Tester l'installation

Démarrez le serveur de développement :

```bash
php artisan serve
```

Ouvrez votre navigateur et allez sur `http://localhost:8000`

Vous devriez voir la page de bienvenue "Bienvenue sur MedKey".

## Configuration pour la production

### 1. Désactiver le mode debug

Dans `.env` :

```env
APP_ENV=production
APP_DEBUG=false
```

### 2. Optimiser l'application

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 3. Configurer les sous-domaines

Voir `SUBDOMAIN_SETUP.md` pour les instructions détaillées.

### 4. Configuration SSL/HTTPS

Utilisez Let's Encrypt pour obtenir des certificats SSL gratuits :

```bash
sudo certbot --apache -d medkey.com -d *.medkey.com
```

## Vérification de l'installation

1. ✅ Page de bienvenue accessible
2. ✅ Formulaire étape 1 fonctionne
3. ✅ Formulaire étape 2 fonctionne
4. ✅ Création de base de données fonctionne
5. ✅ Email envoyé (vérifier avec Mailtrap ou similaire)
6. ✅ Redirection vers sous-domaine fonctionne

## Problèmes courants

### Erreur "Class not found"
```bash
composer dump-autoload
```

### Erreur de permissions
```bash
chmod -R 775 storage bootstrap/cache
```

### Erreur de base de données
- Vérifiez les credentials dans `.env`
- Vérifiez que MySQL est démarré
- Vérifiez que la base de données existe

### Erreur 500
- Vérifiez les logs : `storage/logs/laravel.log`
- Vérifiez les permissions
- Vérifiez la configuration PHP (extensions requises)
