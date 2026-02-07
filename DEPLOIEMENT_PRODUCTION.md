# Guide de Déploiement en Production

## 📋 Commandes à Exécuter en Production

### 1. Après chaque déploiement (obligatoire)

```bash
# Régénérer la documentation Swagger
php artisan l5-swagger:generate

# Optimiser l'application
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Si vous utilisez des queues
php artisan queue:restart
```

### 2. Première installation

```bash
# Installer les dépendances
composer install --no-dev --optimize-autoloader

# Exécuter les migrations
php artisan migrate --force

# Générer la clé d'application (si nécessaire)
php artisan key:generate --force

# Régénérer la documentation Swagger
php artisan l5-swagger:generate

# Optimiser l'application
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

## 🔄 Documentation Swagger

### Option 1 : Génération automatique (recommandé)

**Avantages** :
- La documentation est toujours à jour
- Pas besoin de commiter le fichier JSON

**Configuration** :
Ajoutez dans votre `.env` de production :
```env
L5_SWAGGER_GENERATE_ALWAYS=false
```

Puis, après chaque déploiement, exécutez :
```bash
php artisan l5-swagger:generate
```

### Option 2 : Fichier commité

**Avantages** :
- Pas besoin d'exécuter la commande après déploiement
- Documentation disponible immédiatement

**Inconvénients** :
- Risque d'oublier de mettre à jour le fichier
- Le fichier peut devenir obsolète

Si vous choisissez cette option, commitez `storage/api-docs/api-docs.json` dans votre dépôt Git.

## 📝 Script de Déploiement Recommandé

Créez un script `deploy.sh` :

```bash
#!/bin/bash

# Mettre à jour le code
git pull origin main

# Installer les dépendances
composer install --no-dev --optimize-autoloader

# Exécuter les migrations
php artisan migrate --force

# Régénérer la documentation Swagger
php artisan l5-swagger:generate

# Optimiser l'application
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

# Redémarrer les services (selon votre configuration)
# php artisan queue:restart
# sudo systemctl restart php8.1-fpm
# sudo systemctl restart nginx
```

Rendez-le exécutable :
```bash
chmod +x deploy.sh
```

## ⚠️ Points Importants

1. **Documentation Swagger** : La commande `php artisan l5-swagger:generate` doit être exécutée après chaque déploiement si vous avez modifié les annotations OpenAPI dans vos contrôleurs.

2. **Permissions** : Assurez-vous que le répertoire `storage/api-docs/` est accessible en écriture :
   ```bash
   chmod -R 775 storage/api-docs
   chown -R www-data:www-data storage/api-docs
   ```

3. **Cache** : En production, utilisez toujours les commandes de cache pour améliorer les performances.

4. **Variables d'environnement** : Vérifiez que votre fichier `.env` de production contient toutes les variables nécessaires, notamment :
   ```env
   APP_ENV=production
   APP_DEBUG=false
   L5_SWAGGER_GENERATE_ALWAYS=false
   ```

## 🔍 Vérification Post-Déploiement

Après le déploiement, vérifiez que :

1. ✅ La documentation Swagger est accessible : `https://votre-domaine.com/api/documentation`
2. ✅ Les endpoints API fonctionnent correctement
3. ✅ Les migrations ont été exécutées sans erreur
4. ✅ Le cache a été régénéré

## 📍 URLs de Production

- **Documentation Swagger** : `https://process-onboarding-main-v6bvar.laravel.cloud/api/documentation`
- **API Base URL** : `https://process-onboarding-main-v6bvar.laravel.cloud/api/v1`
