# Réparation de la table MySQL `global_priv` corrompue

## ⚠️ ATTENTION
La table `global_priv` est une table système MySQL critique qui gère les privilèges globaux des utilisateurs. 
Manipuler cette table peut affecter tous les utilisateurs MySQL.

## 🔍 Diagnostic

Si vous obtenez l'erreur :
```
SQLSTATE[HY000]: General error: 1034 Index for table 'global_priv' is corrupt; try to repair it
```

Cela signifie que l'index de la table `global_priv` est corrompu.

## 📋 Procédure de réparation

### Étape 1: Méthodes non-destructives (RECOMMANDÉ)

Exécutez d'abord le script sécurisé :

```bash
mysql -u root -p < repair_mysql_global_priv_safe.sql
```

Ce script tente :
1. `CHECK TABLE` - Vérifie l'état de la table
2. `REPAIR TABLE` - Réparation standard
3. `REPAIR TABLE EXTENDED` - Réparation étendue
4. `OPTIMIZE TABLE` - Optimisation (peut corriger les index)

### Étape 2: Si les méthodes non-destructives échouent

**⚠️ AVANT DE CONTINUER :**
1. **Sauvegardez votre base de données MySQL complète**
2. **Notez tous les utilisateurs MySQL existants et leurs privilèges**

Ensuite, exécutez le script de recréation :

```bash
mysql -u root -p < repair_mysql_global_priv.sql
```

Ce script :
1. Crée une sauvegarde de `global_priv`
2. Supprime la table corrompue
3. Recrée la table avec la structure par défaut
4. Restaure les données depuis la sauvegarde
5. Recharge les privilèges

### Étape 3: Vérification

Après la réparation, vérifiez que tout fonctionne :

```sql
USE mysql;
CHECK TABLE global_priv;
SELECT COUNT(*) FROM global_priv;
FLUSH PRIVILEGES;
```

## 🔄 Alternative : Utiliser mysql_upgrade

Si vous avez MySQL 8.0+, vous pouvez aussi essayer :

```bash
mysql_upgrade -u root -p
```

Cela peut réparer automatiquement les tables système corrompues.

## 🆘 En cas de problème

Si la recréation échoue ou si vous perdez des utilisateurs :

1. **Restaurer depuis une sauvegarde** de la base `mysql`
2. **Recréer manuellement les utilisateurs** si nécessaire
3. **Contacter le support MySQL** pour assistance

## 📝 Notes

- La table `global_priv` existe dans MySQL 8.0+
- Dans MySQL 5.7 et antérieur, c'est la table `user` qui gère les privilèges
- Après toute manipulation, exécutez toujours `FLUSH PRIVILEGES;`
