# Guide d'exécution des tests

Ce guide explique comment exécuter les tests automatisés pour le microservice d'onboarding stateless.

> **Note** : Si vous rencontrez des problèmes avec les tests automatisés (erreur Collision), consultez le [Guide de Tests Manuels](MANUAL_TESTS_GUIDE.md) pour valider toutes les fonctionnalités manuellement.

## ⚠️ Résolution du problème Collision

Si vous obtenez l'erreur `PHPUnit\Event\UnknownSubscriberException` avec `php artisan test` ou `vendor/bin/phpunit`, c'est un problème de compatibilité entre PHPUnit 10.5+ et Collision 7.x.

**Solution 1 : Mettre à jour les dépendances (recommandé)**
```bash
composer update nunomaduro/collision phpunit/phpunit --with-all-dependencies
```

**Solution 2 : Utiliser PHPUnit sans Collision**
Le fichier `tests/bootstrap.php` désactive automatiquement Collision. Si cela ne fonctionne pas, vous pouvez temporairement retirer Collision de `composer.json` :

```json
"require-dev": {
    // "nunomaduro/collision": "^7.0",  // Commenté temporairement
    "phpunit/phpunit": "^10.1",
}
```

Puis exécutez :
```bash
composer update --no-dev
composer require --dev nunomaduro/collision:^7.10 --no-update
composer update nunomaduro/collision
```

## Prérequis

1. **Base de données de test** : Une base de données MySQL dédiée aux tests (configurée dans `.env.testing` ou `phpunit.xml`)
2. **Dépendances** : PHPUnit installé via Composer
3. **Migrations** : Les migrations doivent être à jour

## Configuration

### 1. Base de données de test

Le fichier `phpunit.xml` configure automatiquement :
- `APP_ENV=testing`
- `DB_DATABASE=testing`
- `CACHE_DRIVER=array`
- `SESSION_DRIVER=array`

**Important** : Créez une base de données MySQL nommée `testing` (ou modifiez `phpunit.xml` selon votre configuration).

```sql
CREATE DATABASE testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2. Variables d'environnement

Si nécessaire, créez un fichier `.env.testing` :

```env
APP_ENV=testing
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=testing
DB_USERNAME=root
DB_PASSWORD=
```

## Exécution des tests

### Tous les tests

**⚠️ Important** : Si vous rencontrez une erreur de compatibilité avec Collision, mettez d'abord à jour les dépendances :

```bash
composer update nunomaduro/collision phpunit/phpunit --with-all-dependencies
```

Puis exécutez les tests :

```bash
vendor/bin/phpunit
```

**Alternative** (si `php artisan test` fonctionne après la mise à jour) :
```bash
php artisan test
```

> **Note** : Le fichier `phpunit.xml` est configuré avec un bootstrap personnalisé (`tests/bootstrap.php`) qui désactive Collision pour éviter les conflits de compatibilité.

### Tests spécifiques

**Tests de rate limiting :**
```bash
vendor/bin/phpunit tests/Feature/Api/OnboardingRateLimitTest.php
```

**Tests d'idempotence :**
```bash
vendor/bin/phpunit tests/Feature/Api/OnboardingIdempotenceTest.php
```

**Tests de réponses enrichies :**
```bash
vendor/bin/phpunit tests/Feature/Api/OnboardingEnrichedResponseTest.php
```

**Tests de validation des sous-domaines :**
```bash
vendor/bin/phpunit tests/Feature/SubdomainValidationTest.php
```

### Un test spécifique

```bash
vendor/bin/phpunit --filter test_start_endpoint_rate_limit
```

### Avec couverture de code

```bash
vendor/bin/phpunit --coverage-html coverage
```

### Avec affichage détaillé (testdox)

```bash
vendor/bin/phpunit --testdox
```

Cela affiche les tests de manière plus lisible :
```
Tests\Feature\Api\OnboardingRateLimitTest
  ✓ test start endpoint rate limit
  ✓ test provision endpoint rate limit
  ...
```

## Tests disponibles

### 1. OnboardingRateLimitTest

Teste le rate limiting sur les endpoints :
- `test_start_endpoint_rate_limit` : Vérifie que `/start` limite à 10 requêtes/heure
- `test_provision_endpoint_rate_limit` : Vérifie que `/provision` limite à 1 requête/24h par UUID
- `test_status_endpoint_rate_limit` : Vérifie que `/status` limite à 100 requêtes/heure
- `test_rate_limit_headers` : Vérifie la présence des headers de rate limiting

### 2. OnboardingIdempotenceTest

Teste l'idempotence de `/provision` :
- `test_provision_is_idempotent` : Vérifie que plusieurs appels à `/provision` avec le même UUID retournent le même résultat

### 3. OnboardingEnrichedResponseTest

Teste les réponses enrichies avec metadata :
- `test_start_response_contains_metadata` : Vérifie que `/start` retourne les metadata
- `test_provision_response_contains_metadata` : Vérifie que `/provision` retourne les metadata

### 4. SubdomainValidationTest

Teste la validation des sous-domaines :
- `test_subdomain_validation_format` : Vérifie la validation du format
- `test_subdomain_uniqueness_check` : Vérifie l'unicité
- `test_subdomain_generation_with_retry` : Vérifie le retry automatique

## Dépannage

### Erreur : "Database testing does not exist"

Créez la base de données :
```sql
CREATE DATABASE testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### Erreur : "Class 'Database\Factories\ApplicationFactory' not found"

Exécutez :
```bash
composer dump-autoload
```

### Erreur : "SQLSTATE[HY000] [1045] Access denied"

Vérifiez les credentials dans `.env.testing` ou `phpunit.xml`.

### Erreur : "PHPUnit\Event\UnknownSubscriberException" (Collision)

Si vous rencontrez cette erreur :

```
Fatal error: Uncaught PHPUnit\Event\UnknownSubscriberException: 
Subscriber "PHPUnit\Event\Test\MockObjectForIntersectionOfInterfacesCreatedSubscriber" 
does not exist or is not an interface
```

**Solution 1 : Mettre à jour les dépendances (recommandé)**
```bash
composer update nunomaduro/collision phpunit/phpunit --with-all-dependencies
```

**Solution 2 : Désactiver Collision temporairement**

Modifiez `composer.json` pour retirer temporairement Collision :
```json
"require-dev": {
    // "nunomaduro/collision": "^7.0",  // Commenté
    "phpunit/phpunit": "^10.1",
}
```

Puis :
```bash
composer update --no-dev
```

**Solution 3 : Utiliser une version compatible de Collision**

```bash
composer require --dev nunomaduro/collision:^7.10 --no-update
composer update nunomaduro/collision
```

**Note** : Le fichier `tests/bootstrap.php` définit `PHPUNIT_COLLISION_DISABLED` pour empêcher Collision de s'enregistrer, mais cela peut ne pas suffire si Collision s'enregistre avant le bootstrap.

### Les tests échouent avec "Rate limit not working"

Vérifiez que le cache est bien configuré en `array` pour les tests (déjà fait dans `phpunit.xml`).

## Structure des tests

```
tests/
├── Feature/
│   ├── Api/
│   │   ├── OnboardingRateLimitTest.php
│   │   ├── OnboardingIdempotenceTest.php
│   │   └── OnboardingEnrichedResponseTest.php
│   └── SubdomainValidationTest.php
└── Unit/ (pour les tests unitaires futurs)
```

## Bonnes pratiques

1. **Isolation** : Chaque test utilise `RefreshDatabase` pour garantir l'isolation
2. **Factories** : Utilisez les factories pour créer des données de test
3. **Assertions** : Utilisez des assertions claires et spécifiques
4. **Nettoyage** : Les tests nettoient automatiquement le rate limiter dans `setUp()`

## Exécution en CI/CD

Pour intégrer dans un pipeline CI/CD :

```yaml
# Exemple GitHub Actions
- name: Run tests
  run: |
    composer update nunomaduro/collision phpunit/phpunit --with-all-dependencies
    vendor/bin/phpunit --testdox
```

## Résultat attendu

Un test réussi devrait afficher :

**Avec `--testdox` :**
```
Tests\Feature\Api\OnboardingRateLimitTest
  ✓ test start endpoint rate limit
  ✓ test provision endpoint rate limit
  ✓ test status endpoint rate limit
  ✓ test rate limit headers

Tests\Feature\Api\OnboardingIdempotenceTest
  ✓ test provision is idempotent

Tests\Feature\Api\OnboardingEnrichedResponseTest
  ✓ test start response contains metadata
  ✓ test provision response contains metadata

Tests\Feature\SubdomainValidationTest
  ✓ test subdomain validation format
  ✓ test subdomain uniqueness check
  ✓ test subdomain generation with retry

OK (10 tests, 20 assertions)
```

**Sans `--testdox` :**
```
PHPUnit 10.5.63 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.2.29
Configuration: C:\Users\Akasi\Music\Process_onbording\Process_onbording\phpunit.xml

..........                                                        10 / 10 (100%)

Time: 00:02.456, Memory: 28.00 MB

OK (10 tests, 20 assertions)
```

## Commandes rapides

```bash
# Mettre à jour les dépendances (si erreur Collision)
composer update nunomaduro/collision phpunit/phpunit --with-all-dependencies

# Tous les tests
vendor/bin/phpunit

# Tests avec affichage détaillé et lisible
vendor/bin/phpunit --testdox

# Un seul fichier de test
vendor/bin/phpunit tests/Feature/SubdomainValidationTest.php

# Un seul test spécifique
vendor/bin/phpunit --filter test_start_endpoint_rate_limit

# Arrêter au premier échec (utile pour le debugging)
vendor/bin/phpunit --stop-on-failure

# Avec couverture HTML
vendor/bin/phpunit --coverage-html coverage

# Mode verbeux (affiche plus de détails)
vendor/bin/phpunit --verbose
```

## Résumé

**Pour résoudre l'erreur Collision :**

1. **Mettre à jour les dépendances** (recommandé) :
   ```bash
   composer update nunomaduro/collision phpunit/phpunit --with-all-dependencies
   ```

2. **Puis exécuter les tests** :
   ```bash
   vendor/bin/phpunit --testdox
   ```

Si le problème persiste, consultez la section "Dépannage" ci-dessus pour d'autres solutions.
