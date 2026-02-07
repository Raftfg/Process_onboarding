# Instructions pour réparer la table MySQL `global_priv`

## ⚠️ ATTENTION
Manipuler les tables système MySQL est **risqué**. Faites une **sauvegarde complète** de votre base de données avant de procéder.

## Problème
La table `global_priv` dans MySQL est corrompue, ce qui empêche la création d'utilisateurs MySQL.

## Solutions (par ordre de préférence)

### Solution 1 : Réparation automatique (RECOMMANDÉ)

1. **Arrêter MySQL** (si possible) :
   ```bash
   # Windows (Services)
   net stop MySQL80
   
   # Ou via XAMPP/WAMP
   # Arrêter le service depuis le panneau de contrôle
   ```

2. **Réparer avec `mysql_upgrade`** :
   ```bash
   mysql_upgrade -u root -p
   ```

3. **Redémarrer MySQL** :
   ```bash
   net start MySQL80
   ```

### Solution 2 : Réparation via SQL (si MySQL est accessible)

1. **Se connecter à MySQL en tant que root** :
   ```bash
   mysql -u root -p
   ```

2. **Exécuter le script de réparation sécurisé** :
   ```sql
   source repair_mysql_global_priv_safe.sql
   ```

   Ou exécuter directement :
   ```sql
   USE mysql;
   REPAIR TABLE global_priv;
   CHECK TABLE global_priv;
   FLUSH PRIVILEGES;
   ```

### Solution 3 : Supprimer et recréer (DERNIER RECOURS)

⚠️ **Cette méthode supprime tous les privilèges utilisateurs !**

1. **Se connecter à MySQL en tant que root** :
   ```bash
   mysql -u root -p
   ```

2. **Sauvegarder les données** (si possible) :
   ```sql
   USE mysql;
   CREATE TABLE global_priv_backup AS SELECT * FROM global_priv;
   ```

3. **Exécuter le script de recréation** :
   ```sql
   source repair_mysql_global_priv.sql
   ```

   Ou exécuter manuellement :
   ```sql
   USE mysql;
   
   -- Sauvegarder
   CREATE TABLE global_priv_backup AS SELECT * FROM global_priv;
   
   -- Supprimer
   DROP TABLE global_priv;
   
   -- Recréer
   CREATE TABLE global_priv (
     Host CHAR(255) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT '',
     User CHAR(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin NOT NULL DEFAULT '',
     Priv LONGTEXT CHARACTER SET utf8mb3 COLLATE utf8mb3_bin NOT NULL,
     PRIMARY KEY (Host, User)
   ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin COMMENT='Global privileges';
   
   -- Restaurer (si sauvegarde réussie)
   INSERT INTO global_priv (Host, User, Priv)
   SELECT Host, User, Priv FROM global_priv_backup;
   
   -- Ou créer au minimum root
   INSERT IGNORE INTO global_priv (Host, User, Priv)
   VALUES 
     ('localhost', 'root', '{"access": 18446744073709551615}'),
     ('127.0.0.1', 'root', '{"access": 18446744073709551615}');
   
   FLUSH PRIVILEGES;
   ```

## Vérification

Après la réparation, vérifiez que tout fonctionne :

```sql
USE mysql;
CHECK TABLE global_priv;
SELECT COUNT(*) FROM global_priv;
FLUSH PRIVILEGES;
```

Puis testez la création d'un utilisateur :

```sql
CREATE USER 'test_user'@'localhost' IDENTIFIED BY 'test_password';
DROP USER 'test_user'@'localhost';
```

Si cela fonctionne, vous pouvez tester votre endpoint Laravel :

```bash
POST /api/v1/applications/{app_id}/retry-database
Headers: X-Master-Key: {votre_master_key}
```

## En cas de problème

Si après la réparation vous ne pouvez plus vous connecter à MySQL :

1. **Arrêter MySQL**
2. **Démarrer MySQL en mode récupération** :
   ```bash
   mysqld --skip-grant-tables
   ```
3. **Se connecter sans mot de passe** :
   ```bash
   mysql -u root
   ```
4. **Réparer manuellement** ou **restaurer depuis une sauvegarde**

## Notes importantes

- La structure de `global_priv` peut varier selon la version de MySQL
- Pour MySQL 5.7 et antérieur, la table s'appelle `user` (pas `global_priv`)
- Vérifiez votre version MySQL avec : `SELECT VERSION();`
