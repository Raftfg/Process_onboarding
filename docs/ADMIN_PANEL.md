# Interface d'Administration - Documentation

## 📋 Vue d'ensemble

L'interface d'administration permet de gérer tous les tenants de la plateforme MedKey depuis un seul endroit. Elle offre une vue complète sur les tenants, leurs statistiques et permet d'effectuer des actions de gestion.

## 🔐 Authentification

### Configuration

Pour accéder à l'interface admin, vous devez configurer les variables d'environnement dans votre fichier `.env` :

```env
ADMIN_EMAIL=admin@medkey.local
ADMIN_PASSWORD=votre_mot_de_passe_securise
```

**⚠️ Important** : Pour la production, utilisez un système d'authentification plus robuste (table `users` avec rôle admin, 2FA, etc.)

### Connexion

1. Accédez à `/admin/login`
2. Entrez l'email et le mot de passe configurés
3. Vous serez redirigé vers le dashboard admin

## 📊 Fonctionnalités

### Dashboard Admin (`/admin/dashboard`)

Le dashboard affiche :
- **Statistiques globales** :
  - Total des tenants
  - Tenants actifs
  - Tenants suspendus
  - Tenants inactifs
- **Liste des tenants récents** : Les 5 derniers tenants créés

### Liste des Tenants (`/admin/tenants`)

Affiche tous les tenants avec :
- **Filtres** :
  - Recherche par nom, sous-domaine, email
  - Filtre par statut (actif, suspendu, inactif)
  - Tri par date de création, nom, statut
- **Pagination** : 15 tenants par page
- **Informations affichées** :
  - ID
  - Nom
  - Sous-domaine
  - Email
  - Statut
  - Plan
  - Date de création

### Détails d'un Tenant (`/admin/tenants/{id}`)

Affiche les informations complètes d'un tenant :

#### Informations générales
- Statut actuel
- Sous-domaine
- Nom de la base de données
- Plan
- Nom, email, téléphone, adresse
- Dates de création et modification

#### Statistiques du Tenant
- Nombre total d'utilisateurs
- Nombre d'administrateurs
- Nombre d'utilisateurs réguliers
- Nombre de managers
- Nombre de personnes enregistrées
- Nombre de configurations dashboard

#### Actions disponibles
- **Activer** : Change le statut à `active`
- **Suspendre** : Change le statut à `suspended`
- **Désactiver** : Change le statut à `inactive`
- **Supprimer** : Suppression douce (soft delete)
- **Restaurer** : Restaure un tenant supprimé

## 🔒 Sécurité

### Middleware `EnsureAdmin`

Le middleware `EnsureAdmin` protège toutes les routes admin. Il vérifie :
1. Si l'email correspond à `ADMIN_EMAIL` dans `.env`
2. Si une session admin est active
3. Si l'utilisateur a un rôle admin dans la base principale (si applicable)

### Protection des routes

Toutes les routes admin sont protégées par :
- Middleware `admin` (alias de `EnsureAdmin`)
- Vérification de session
- Isolation de la base de données (toujours utiliser la base principale)

## 🛠️ Utilisation

### Accéder au panel admin

```bash
# URL locale
http://localhost:8000/admin/login

# URL production
https://votre-domaine.com/admin/login
```

### Gérer un tenant

1. **Voir tous les tenants** : `/admin/tenants`
2. **Filtrer les tenants** : Utilisez les filtres en haut de la page
3. **Voir les détails** : Cliquez sur "Voir" dans la liste
4. **Changer le statut** : Utilisez les boutons d'action dans la page de détails
5. **Supprimer un tenant** : Cliquez sur "Supprimer" (confirmation requise)

### Actions sur les statuts

- **Actif** : Le tenant est opérationnel, les utilisateurs peuvent se connecter
- **Suspendu** : Le tenant est temporairement indisponible (maintenance, problème de paiement, etc.)
- **Inactif** : Le tenant est désactivé mais peut être réactivé

## 📝 Routes disponibles

| Route | Méthode | Description |
|-------|---------|--------------|
| `/admin/login` | GET | Formulaire de connexion |
| `/admin/login` | POST | Traitement de la connexion |
| `/admin/logout` | POST | Déconnexion |
| `/admin/dashboard` | GET | Dashboard avec statistiques |
| `/admin/tenants` | GET | Liste des tenants |
| `/admin/tenants/{id}` | GET | Détails d'un tenant |
| `/admin/tenants/{id}/status` | POST | Mettre à jour le statut |
| `/admin/tenants/{id}` | DELETE | Supprimer un tenant |
| `/admin/tenants/{id}/restore` | POST | Restaurer un tenant |

## 🔧 Améliorations futures

- [ ] Authentification avec table `users` et rôle admin
- [ ] 2FA (Two-Factor Authentication)
- [ ] Logs d'audit pour toutes les actions admin
- [ ] Export des données des tenants
- [ ] Import de tenants
- [ ] Gestion des plans et facturation
- [ ] Notifications pour les actions importantes
- [ ] Recherche avancée avec plusieurs critères
- [ ] Graphiques et statistiques détaillées
- [ ] Gestion des permissions admin (super admin, admin, etc.)

## ⚠️ Notes importantes

1. **Base de données** : Le système s'assure toujours d'utiliser la base principale (`mysql`) pour les opérations admin
2. **Cache** : Le cache des tenants est automatiquement nettoyé lors des modifications
3. **Logs** : Toutes les actions importantes sont loggées
4. **Soft Delete** : La suppression est douce, les données peuvent être restaurées
5. **Statistiques** : Les statistiques sont récupérées depuis la base du tenant, en cas d'erreur, un message est affiché

## 🐛 Dépannage

### Impossible de se connecter

1. Vérifiez que `ADMIN_EMAIL` et `ADMIN_PASSWORD` sont bien configurés dans `.env`
2. Vérifiez que les valeurs correspondent exactement
3. Videz le cache : `php artisan cache:clear`
4. Vérifiez les logs : `storage/logs/laravel.log`

### Les statistiques ne s'affichent pas

1. Vérifiez que la base de données du tenant existe
2. Vérifiez que les migrations ont été exécutées
3. Vérifiez les logs pour les erreurs de connexion

### Le middleware bloque l'accès

1. Vérifiez que la session admin est active
2. Vérifiez que vous êtes bien connecté avec le bon email
3. Vérifiez que le middleware `admin` est bien enregistré dans `Kernel.php`

## 📚 Code

### Contrôleurs

- `App\Http\Controllers\Admin\TenantController` : Gestion des tenants
- `App\Http\Controllers\Admin\AuthController` : Authentification admin

### Middleware

- `App\Http\Middleware\EnsureAdmin` : Protection des routes admin

### Vues

- `resources/views/admin/auth/login.blade.php` : Formulaire de connexion
- `resources/views/admin/dashboard.blade.php` : Dashboard admin
- `resources/views/admin/tenants/index.blade.php` : Liste des tenants
- `resources/views/admin/tenants/show.blade.php` : Détails d'un tenant

