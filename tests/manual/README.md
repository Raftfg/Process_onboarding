# Tests Manuels - Guide Rapide

## Démarrage Rapide

### 1. Préparer l'environnement

```bash
# Démarrer le serveur Laravel
php artisan serve

# Dans un autre terminal, vérifier que le serveur répond
curl http://127.0.0.1:8000
```

### 2. Enregistrer une application

**Via l'interface web** :
1. Ouvrir `http://127.0.0.1:8000/applications/register`
2. Remplir le formulaire
3. Sauvegarder la **master key** affichée

**Via l'API** :
```bash
curl -X POST http://127.0.0.1:8000/api/v1/applications/register \
  -H "Content-Type: application/json" \
  -d '{
    "app_name": "test-app",
    "display_name": "Application de Test",
    "contact_email": "test@example.com"
  }'
```

### 3. Exécuter les tests

**Avec le script PowerShell** :
```powershell
.\tests\manual\test_script.ps1 -MasterKey "mk_votre_master_key_ici"
```

**Avec cURL manuellement** :
Voir `MANUAL_TESTS_GUIDE.md` pour les commandes détaillées.

## Tests Essentiels à Valider

### ✅ Rate Limiting
- [ ] `/start` bloque après 10 requêtes
- [ ] `/provision` bloque après 1 requête par UUID
- [ ] Headers `X-RateLimit-*` présents

### ✅ Réponses Enrichies
- [ ] `/start` retourne `metadata`
- [ ] `/provision` retourne `metadata` avec `is_idempotent`
- [ ] `/status` retourne `metadata` complète

### ✅ Validation Sous-domaines
- [ ] Sous-domaines uniques générés
- [ ] Format valide (minuscules, tirets)
- [ ] Retry automatique en cas de conflit

### ✅ Idempotence
- [ ] `/provision` idempotent (appels multiples = même résultat)
- [ ] `is_idempotent` = `true` lors d'appels répétés

### ✅ Interface Web
- [ ] Enregistrement d'application fonctionne
- [ ] Dashboard affiche les statistiques
- [ ] Master key affichée une seule fois

### ✅ Dashboard Admin
- [ ] Monitoring accessible
- [ ] Filtres fonctionnent
- [ ] Export CSV fonctionne

## Commandes Utiles

```bash
# Voir les onboardings en base
mysql -u root -p -e "SELECT uuid, email, subdomain, status, provisioning_attempts FROM onboarding_registrations ORDER BY created_at DESC LIMIT 10;" <database_name>

# Voir les logs
tail -f storage/logs/laravel.log

# Nettoyer les données de test
mysql -u root -p -e "DELETE FROM onboarding_registrations WHERE email LIKE 'test%@example.com';" <database_name>
```

## Documentation Complète

- **Guide détaillé** : `MANUAL_TESTS_GUIDE.md`
- **Scénarios complets** : `tests/manual/test_scenarios.md`
