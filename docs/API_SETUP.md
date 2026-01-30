# Configuration de l'API - Guide Administrateur

Ce guide explique comment configurer et gérer l'API pour les intégrations externes.

## 🔑 Génération de clés API

### Méthode 1 : Via Artisan (Recommandé)

```bash
# Générer une clé API simple
php artisan api:generate-key "Application Production"

# Générer une clé avec expiration
php artisan api:generate-key "Application Test" --expires="2024-12-31 23:59:59"

# Générer une clé avec limite de taux
php artisan api:generate-key "Application Limitée" --limit=50
```

### Méthode 2 : Via variable d'environnement

Ajoutez dans `.env` :
```env
API_KEY=your_secret_api_key_here
```

⚠️ **Note:** Cette méthode est moins flexible (une seule clé, pas de gestion fine).

### Méthode 3 : Via code PHP

```php
use App\Models\ApiKey;

$result = ApiKey::generate('Mon Application', [
    'expires_at' => now()->addYear(),
    'rate_limit' => 100,
    'allowed_ips' => ['192.168.1.100'], // Optionnel
]);

// Sauvegarder $result['key'] immédiatement !
echo $result['key'];
```

## 📋 Gestion des clés

### Lister toutes les clés

```bash
php artisan api:list-keys
```

### Désactiver une clé

```php
use App\Models\ApiKey;

$apiKey = ApiKey::find($id);
$apiKey->update(['is_active' => false]);
```

### Vérifier l'utilisation

```php
$apiKey = ApiKey::find($id);
echo "Dernière utilisation: " . $apiKey->last_used_at;
echo "Limite: " . $apiKey->rate_limit . " req/min";
```

## 🔒 Sécurité

### Restrictions par IP

```php
$result = ApiKey::generate('Application Sécurisée', [
    'allowed_ips' => [
        '192.168.1.100',
        '10.0.0.50',
    ],
]);
```

### Expiration automatique

Les clés expirées sont automatiquement rejetées. Vérifiez régulièrement :

```php
$expiredKeys = ApiKey::where('expires_at', '<', now())
    ->where('is_active', true)
    ->get();
```

## 🚀 Migration

Pour activer le système de clés API en base de données :

```bash
php artisan migrate
```

Cela créera la table `api_keys` pour une gestion avancée.

## 📊 Monitoring

### Logs d'accès

Les tentatives d'accès avec des clés invalides sont loggées :

```bash
tail -f storage/logs/laravel.log | grep "Tentative d'accès"
```

### Statistiques

```php
use App\Models\ApiKey;

// Clés les plus utilisées
$topKeys = ApiKey::orderBy('last_used_at', 'desc')
    ->take(10)
    ->get();
```

## 🔔 Configuration des Webhooks

### Créer un webhook via API

```bash
curl -X POST https://onboarding.medkey.com/api/webhooks/register \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://votre-app.com/webhooks/onboarding",
    "events": ["onboarding.completed", "onboarding.failed"]
  }'
```

### Tester un webhook localement

Utilisez [ngrok](https://ngrok.com/) pour exposer votre serveur local :

```bash
ngrok http 3000
# Utilisez l'URL ngrok dans la configuration du webhook
```

## ⚙️ Configuration avancée

### Rate Limiting

Le rate limiting est géré par clé API. Modifiez la limite :

```php
$apiKey = ApiKey::find($id);
$apiKey->update(['rate_limit' => 200]);
```

### Timeout des webhooks

Par défaut, les webhooks ont un timeout de 30 secondes. Modifiez-le :

```php
$webhook = Webhook::find($id);
$webhook->update(['timeout' => 60]);
```

## 🐛 Dépannage

### Clé API rejetée

1. Vérifiez que la clé est active : `php artisan api:list-keys`
2. Vérifiez l'expiration : `$apiKey->expires_at`
3. Vérifiez les restrictions IP : `$apiKey->allowed_ips`

### Webhooks non reçus

1. Vérifiez que le webhook est actif
2. Vérifiez les logs : `storage/logs/laravel.log`
3. Testez l'URL du webhook manuellement
4. Vérifiez le timeout (peut être trop court)

## 📝 Exemple complet

```php
use App\Models\ApiKey;
use App\Services\WebhookService;

// 1. Créer une clé API
$apiKey = ApiKey::generate('Mon Application', [
    'expires_at' => now()->addYear(),
    'rate_limit' => 100,
]);

// 2. Créer un webhook
$webhookService = app(WebhookService::class);
$webhook = $webhookService->create([
    'api_key_id' => $apiKey['id'],
    'url' => 'https://mon-app.com/webhooks',
    'events' => ['onboarding.completed'],
]);

echo "Clé API: " . $apiKey['key'] . "\n";
echo "Secret webhook: " . $webhook->secret . "\n";
```
