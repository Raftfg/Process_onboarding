# Guide de Test - Akasi Group Onboarding

Ce guide explique comment tester le système d'onboarding.

## 🚀 Tests Rapides

### Commande Artisan

La méthode la plus simple pour tester le système :

```bash
php artisan test:onboarding
```

Cette commande va :
1. Créer un sous-domaine de test
2. Créer une base de données de test
3. Exécuter le processus d'onboarding complet
4. Vérifier la création de l'utilisateur
5. Tester l'authentification
6. Afficher un résumé des résultats

### Options disponibles

```bash
# Nettoyer automatiquement les données de test après
php artisan test:onboarding --clean

# Utiliser un sous-domaine spécifique
php artisan test:onboarding --subdomain=mon-test-personnalise
```

## 📋 Scripts Shell

### Linux/Mac

```bash
# Rendre le script exécutable (première fois seulement)
chmod +x test-onboarding.sh

# Exécuter les tests
./test-onboarding.sh

# Avec nettoyage
./test-onboarding.sh --clean
```

### Windows

```cmd
test-onboarding.bat

REM Avec nettoyage
test-onboarding.bat --clean
```

## 🧪 Tests PHPUnit

Pour des tests plus approfondis avec PHPUnit :

```bash
# Tous les tests d'onboarding
php artisan test --filter OnboardingTest

# Un test spécifique
php artisan test --filter it_can_create_admin_user_in_tenant_database
```

## ✅ Ce qui est testé

### 1. Création de la base de données
- Vérifie que la base de données peut être créée
- Vérifie que la base existe après création

### 2. Processus d'onboarding complet
- Teste le service `OnboardingService`
- Vérifie la génération du sous-domaine
- Vérifie la création de la base de données
- Vérifie l'enregistrement de la session

### 3. Création de l'utilisateur admin
- Vérifie que l'utilisateur est créé dans la base tenant
- Vérifie les informations de l'utilisateur
- Vérifie le hashage du mot de passe

### 4. Basculement entre bases de données
- Teste le passage de la base principale à la base tenant
- Vérifie le retour à la base principale
- Vérifie que les données sont isolées

### 5. Authentification
- Teste la vérification du mot de passe
- Vérifie que l'utilisateur peut s'authentifier

### 6. Session d'onboarding
- Vérifie que la session est enregistrée dans la base principale
- Vérifie les données de la session

## 🔍 Résultats des tests

Après l'exécution, vous verrez un résumé comme :

```
📊 Résumé des tests:

  ✅ testDatabaseCreation
  ✅ testOnboardingProcess
  ✅ testUserCreation
  ✅ testDatabaseSwitch
  ✅ testUserAuthentication
  ✅ testOnboardingSession

✅ Succès: 6
```

## 🧹 Nettoyage

### Nettoyage automatique

Utilisez l'option `--clean` pour supprimer automatiquement :
- La session d'onboarding de test
- La base de données de test

```bash
php artisan test:onboarding --clean
```

### Nettoyage manuel

Si vous avez oublié d'utiliser `--clean`, vous pouvez nettoyer manuellement :

```sql
-- Supprimer la session
DELETE FROM onboarding_sessions WHERE subdomain LIKE 'test-%';

-- Supprimer la base de données
DROP DATABASE IF EXISTS akasigroup_test-XXXXXX;
```

## 🐛 Dépannage

### Erreur : "Base de données existe déjà"

Si vous voyez cette erreur, utilisez `--clean` ou supprimez manuellement la base de données.

### Erreur : "Permissions insuffisantes"

Vérifiez que `DB_ROOT_USERNAME` et `DB_ROOT_PASSWORD` dans `.env` ont les droits de création de bases de données.

### Erreur : "Utilisateur non trouvé"

Cela peut arriver si le processus d'onboarding a échoué. Vérifiez les logs dans `storage/logs/laravel.log`.

## 📝 Notes

- Les tests créent des données réelles dans votre base de données
- Utilisez toujours `--clean` en développement
- En production, ne lancez jamais les tests sur la base de données principale
- Les sous-domaines de test sont générés avec le préfixe `test-` suivi d'un timestamp

## 🔗 Voir aussi

- [Guide d'intégration](INTEGRATION.md)
- [Configuration API](API_SETUP.md)
- [Documentation principale](README.md)
