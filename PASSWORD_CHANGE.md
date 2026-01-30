# Changement de mot de passe obligatoire

## Fonctionnalité

Après la première connexion, les utilisateurs sont **obligés** de changer leur mot de passe avant d'accéder au dashboard.

## Fonctionnement

1. **Création de l'utilisateur** : Lors de l'onboarding, l'utilisateur admin est créé avec `password_changed_at = NULL`
2. **Première connexion** : Après connexion, le système vérifie si `password_changed_at` est `NULL`
3. **Redirection** : Si `NULL`, l'utilisateur est redirigé vers `/change-password`
4. **Middleware** : Le middleware `ForcePasswordChange` bloque l'accès au dashboard tant que le mot de passe n'a pas été changé
5. **Changement** : Une fois le mot de passe changé, `password_changed_at` est mis à jour avec la date actuelle

## Routes

- `GET /change-password` : Affiche le formulaire de changement de mot de passe
- `POST /change-password` : Traite le changement de mot de passe

## Sécurité

- Vérification du mot de passe actuel
- Le nouveau mot de passe doit être différent de l'ancien
- Minimum 8 caractères requis
- Le middleware bloque toutes les routes protégées sauf `/change-password` et `/logout`

## Migration

La colonne `password_changed_at` a été ajoutée à la table `users` via la migration :
- `2026_01_30_152015_add_password_changed_at_to_users_table.php`

Pour mettre à jour les bases de données existantes :
```bash
php artisan tenants:update-databases
```
