# Exemple de Commande cURL pour l'Enregistrement d'Application

## ⚠️ Erreur Courante

Si vous obtenez une erreur `422` avec le message `"validation.unique"` pour `app_name`, cela signifie que le nom d'application existe déjà dans la base de données.

## ✅ Commande cURL Corrigée

### Option 1 : Utiliser un nom unique (recommandé)

```bash
curl -X 'POST' \
  'https://process-onboarding-main-v6bvar.laravel.cloud/api/v1/applications/register' \
  -H 'accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{
  "app_name": "mon-application-'$(date +%s)'",
  "display_name": "Mon Application",
  "contact_email": "dev@monapp.com",
  "website": "https://monapp.com"
}'
```

### Option 2 : Utiliser un nom personnalisé unique

```bash
curl -X 'POST' \
  'https://process-onboarding-main-v6bvar.laravel.cloud/api/v1/applications/register' \
  -H 'accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{
  "app_name": "mon-app-unique-2026",
  "display_name": "Mon Application",
  "contact_email": "dev@monapp.com",
  "website": "https://monapp.com"
}'
```

### Option 3 : Version PowerShell (Windows)

```powershell
$timestamp = Get-Date -Format "yyyyMMddHHmmss"
$body = @{
    app_name = "mon-application-$timestamp"
    display_name = "Mon Application"
    contact_email = "dev@monapp.com"
    website = "https://monapp.com"
} | ConvertTo-Json

Invoke-RestMethod -Uri "https://process-onboarding-main-v6bvar.laravel.cloud/api/v1/applications/register" `
    -Method POST `
    -ContentType "application/json" `
    -Body $body
```

## 📝 Règles de Validation

- **app_name** :
  - Doit être unique (pas déjà utilisé)
  - Maximum 50 caractères
  - Uniquement lettres, chiffres, tirets (`-`) et underscores (`_`)
  - Ne peut pas être un nom réservé : `admin`, `api`, `www`, `mail`, `ftp`, `localhost`, `test`, `dev`, `staging`, `prod`

- **display_name** : Maximum 255 caractères

- **contact_email** : Doit être une adresse email valide

- **website** : Optionnel, doit être une URL valide si fourni

## 🔍 Messages d'Erreur Améliorés

Les messages d'erreur sont maintenant plus clairs :

- `"Ce nom d'application est déjà utilisé. Veuillez choisir un autre nom."` - Si le nom existe déjà
- `"Le nom d'application ne peut contenir que des lettres, chiffres, tirets et underscores."` - Si le format est invalide
- `"Le nom d'application ne peut pas dépasser 50 caractères."` - Si trop long
- `"Ce nom d'application est réservé. Veuillez en choisir un autre."` - Si nom réservé

## ✅ Réponse de Succès

```json
{
  "success": true,
  "message": "Application enregistrée avec succès",
  "application": {
    "app_id": "app_abc123...",
    "app_name": "mon-application-1234567890",
    "display_name": "Mon Application",
    "contact_email": "dev@monapp.com",
    "website": "https://monapp.com",
    "created_at": "2026-02-07T11:52:00Z"
  },
  "master_key": "mk_live_xyz789...",
  "warnings": [
    "⚠️ IMPORTANT: Sauvegardez la master_key immédiatement ! Elle ne sera plus jamais affichée.",
    "💡 Vous pouvez maintenant utiliser cette master_key pour démarrer un onboarding avec POST /api/v1/onboarding/start"
  ]
}
```

## 🚀 Prochaines Étapes

Une fois l'application enregistrée, utilisez la `master_key` pour démarrer un onboarding :

```bash
curl -X 'POST' \
  'https://process-onboarding-main-v6bvar.laravel.cloud/api/v1/onboarding/start' \
  -H 'accept: application/json' \
  -H 'Content-Type: application/json' \
  -H 'X-Master-Key: mk_live_xyz789...' \
  -d '{
  "email": "admin@example.com",
  "organization_name": "Mon Organisation"
}'
```
