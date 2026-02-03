# Guide d'Intégration - Onboarding Externe

Ce guide explique **étape par étape** comment intégrer le microservice d'onboarding dans votre application tierce (SIH, ERP, etc.).

## 🎯 Cas d'Usage

Votre application veut créer automatiquement un espace client (tenant) dans le système Akasi Group pour chaque nouvelle organisation que vous gérez.

**Exemple concret** : Vous avez un logiciel de gestion hospitalière et vous voulez que chaque nouvel hôpital qui s'inscrit chez vous obtienne automatiquement son propre espace Akasi Group avec ses tables personnalisées.

---

## 📋 Prérequis

### 1. Obtenir une Clé API
Contactez l'administrateur du microservice pour obtenir :
- Une **clé API** (`X-API-Key`)
- Un **identifiant d'application** unique (`X-App-Name`)

**Exemple** :
- `X-API-Key`: `sk_live_abc123def456...`
- `X-App-Name`: `Secteur-Sante-v1`

### 2. Préparer votre Endpoint de Callback (Optionnel)
Si vous voulez être notifié quand le tenant est prêt, créez un endpoint dans votre application :
```
POST https://votre-app.com/api/tenants/confirm
```

---

## 🚀 Étape 1 : Construire la Requête

### Headers Obligatoires
```http
POST /api/v1/onboarding/external HTTP/1.1
Host: onboarding.akasigroup.com
Content-Type: application/json
X-API-Key: sk_live_abc123def456...
X-App-Name: Secteur-Sante-v1
```

### Corps de la Requête (JSON)

#### Champs Obligatoires
```json
{
  "email": "admin@clinique-du-lac.com",
  "organization_name": "Clinique Du Lac"
}
```

| Champ | Type | Description |
|-------|------|-------------|
| `email` | string | Email de l'administrateur du nouveau tenant |
| `organization_name` | string | Nom unique de l'organisation (unique par `X-App-Name`) |

#### Champs Optionnels

##### 1. **callback_url** - Pour recevoir une notification
```json
{
  "callback_url": "https://votre-app.com/api/tenants/confirm"
}
```
Le microservice enverra une requête `POST` à cette URL une fois le tenant créé.

##### 2. **metadata** - Pour stocker vos propres données
```json
{
  "metadata": {
    "external_id": "SIH-123456",
    "plan": "premium",
    "region": "Libreville"
  }
}
```
Vous recevrez ces données dans le callback.

##### 3. **migrations** - Pour créer des tables personnalisées
```json
{
  "migrations": [
    {
      "filename": "2026_02_03_create_patients_table.php",
      "content": "<?php\nuse Illuminate\\Database\\Migrations\\Migration;\nuse Illuminate\\Database\\Schema\\Blueprint;\nuse Illuminate\\Support\\Facades\\Schema;\n\nreturn new class extends Migration {\n    public function up() {\n        Schema::create('patients', function (Blueprint $table) {\n            $table->id();\n            $table->string('nom');\n            $table->string('prenom');\n            $table->date('date_naissance');\n            $table->timestamps();\n        });\n    }\n};"
    }
  ]
}
```

---

## 🔄 Étape 2 : Envoyer la Requête

### Exemple en PHP (avec Guzzle)
```php
use GuzzleHttp\Client;

$client = new Client();

$response = $client->post('https://onboarding.akasigroup.com/api/v1/onboarding/external', [
    'headers' => [
        'X-API-Key' => 'sk_live_abc123def456...',
        'X-App-Name' => 'Secteur-Sante-v1',
        'Content-Type' => 'application/json',
    ],
    'json' => [
        'email' => 'admin@clinique-du-lac.com',
        'organization_name' => 'Clinique Du Lac',
        'callback_url' => 'https://votre-app.com/api/tenants/confirm',
        'metadata' => [
            'external_id' => 'SIH-123456',
        ],
        'migrations' => [
            [
                'filename' => '2026_02_03_create_patients_table.php',
                'content' => file_get_contents(__DIR__ . '/migrations/patients.php'),
            ],
        ],
    ],
]);

$result = json_decode($response->getBody(), true);
```

### Exemple en JavaScript (Node.js)
```javascript
const axios = require('axios');

const response = await axios.post(
  'https://onboarding.akasigroup.com/api/v1/onboarding/external',
  {
    email: 'admin@clinique-du-lac.com',
    organization_name: 'Clinique Du Lac',
    callback_url: 'https://votre-app.com/api/tenants/confirm',
    metadata: {
      external_id: 'SIH-123456',
    },
    migrations: [
      {
        filename: '2026_02_03_create_patients_table.php',
        content: fs.readFileSync('./migrations/patients.php', 'utf8'),
      },
    ],
  },
  {
    headers: {
      'X-API-Key': 'sk_live_abc123def456...',
      'X-App-Name': 'Secteur-Sante-v1',
      'Content-Type': 'application/json',
    },
  }
);

console.log(response.data);
```

---

## ✅ Étape 3 : Traiter la Réponse

### Réponse Immédiate (Succès)
```json
{
  "success": true,
  "message": "Onboarding externe initié avec succès",
  "result": {
    "subdomain": "clinique-du-lac",
    "activation_token": "abc123xyz789...",
    "url": "http://clinique-du-lac.localhost:8000"
  }
}
```

| Champ | Description |
|-------|-------------|
| `subdomain` | Sous-domaine généré pour le tenant |
| `activation_token` | Token pour activer le compte (envoyé par email) |
| `url` | URL d'accès au tenant |

### Réponse d'Erreur
```json
{
  "success": false,
  "message": "Une organisation avec le nom 'Clinique Du Lac' existe déjà pour l'application Secteur-Sante-v1."
}
```

---

## 🔔 Étape 4 : Recevoir le Callback (Optionnel)

Si vous avez fourni un `callback_url`, vous recevrez cette requête une fois le tenant créé :

### Requête POST vers votre endpoint
```http
POST /api/tenants/confirm HTTP/1.1
Host: votre-app.com
Content-Type: application/json

{
  "success": true,
  "subdomain": "clinique-du-lac",
  "database": "tenant_clinique_du_lac",
  "url": "http://clinique-du-lac.localhost:8000",
  "email": "admin@clinique-du-lac.com",
  "organization_name": "Clinique Du Lac",
  "activation_token": "abc123xyz789...",
  "metadata": {
    "external_id": "SIH-123456"
  }
}
```

### Exemple de Handler (PHP Laravel)
```php
public function handleTenantConfirmation(Request $request)
{
    $data = $request->all();
    
    // Récupérer votre ID interne
    $externalId = $data['metadata']['external_id']; // "SIH-123456"
    
    // Mettre à jour votre base de données
    Hospital::where('id', $externalId)->update([
        'akasi_subdomain' => $data['subdomain'],
        'akasi_url' => $data['url'],
        'akasi_status' => 'active',
    ]);
    
    // Envoyer un email à l'admin avec le lien d'activation
    Mail::to($data['email'])->send(new TenantReadyMail($data));
    
    return response()->json(['status' => 'ok']);
}
```

---

## 🔐 Point Important : Isolation par Application

> [!IMPORTANT]
> Le nom de l'organisation est unique **par application** (`X-App-Name`).

**Ce que cela signifie** :
- ✅ `App-1` peut créer "Clinique Du Lac"
- ✅ `App-2` peut AUSSI créer "Clinique Du Lac" (pas de conflit)
- ❌ `App-1` ne peut PAS créer "Clinique Du Lac" deux fois

**Pourquoi c'est important** :
Plusieurs applications peuvent utiliser le même microservice sans risque de collision de noms.

---

## 📝 Exemple Complet de Workflow

### Scénario : Nouvel hôpital s'inscrit dans votre SIH

```php
// 1. L'hôpital remplit le formulaire d'inscription
$hospital = Hospital::create([
    'name' => 'Clinique Du Lac',
    'email' => 'admin@clinique-du-lac.com',
]);

// 2. Appeler le microservice d'onboarding
$client = new GuzzleHttp\Client();
$response = $client->post('https://onboarding.akasigroup.com/api/v1/onboarding/external', [
    'headers' => [
        'X-API-Key' => config('akasi.api_key'),
        'X-App-Name' => 'SIH-Gabon-v2',
    ],
    'json' => [
        'email' => $hospital->email,
        'organization_name' => $hospital->name,
        'callback_url' => route('akasi.callback'),
        'metadata' => [
            'external_id' => $hospital->id,
            'plan' => 'premium',
        ],
        'migrations' => [
            [
                'filename' => '2026_create_patients.php',
                'content' => file_get_contents(resource_path('akasi/migrations/patients.php')),
            ],
        ],
    ],
]);

$result = json_decode($response->getBody(), true);

// 3. Sauvegarder les infos du tenant
$hospital->update([
    'akasi_subdomain' => $result['result']['subdomain'],
    'akasi_url' => $result['result']['url'],
    'akasi_status' => 'pending',
]);

// 4. Attendre le callback pour confirmer
// (Votre endpoint /akasi/callback sera appelé automatiquement)
```

---

## 🛠 Debugging

### Vérifier que votre requête est correcte
```bash
curl -X POST https://onboarding.akasigroup.com/api/v1/onboarding/external \
  -H "X-API-Key: sk_live_abc123..." \
  -H "X-App-Name: Secteur-Sante-v1" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "organization_name": "Test Org"
  }'
```

### Erreurs Courantes

| Erreur | Cause | Solution |
|--------|-------|----------|
| `401 Unauthorized` | Clé API invalide | Vérifier `X-API-Key` |
| `400 Bad Request: X-App-Name obligatoire` | Header manquant | Ajouter `X-App-Name` |
| `409 Conflict` | Organisation existe déjà | Changer le nom ou vérifier l'app |
| `500 Internal Server Error` | Migration SQL invalide | Vérifier la syntaxe de la migration |

---

## 📞 Support

Pour toute question ou problème d'intégration, contactez l'équipe Akasi Group.
