-- Script SÉCURISÉ de réparation de la table global_priv
-- Version alternative qui tente d'abord des méthodes non-destructives

USE mysql;

-- Méthode 1: Vérification et réparation standard
SELECT '=== Étape 1: Vérification de la table ===' AS step;
CHECK TABLE global_priv;

SELECT '=== Étape 2: Tentative de réparation standard ===' AS step;
REPAIR TABLE global_priv;

-- Si la réparation standard échoue, essayer avec EXTENDED
SELECT '=== Étape 3: Tentative de réparation étendue ===' AS step;
REPAIR TABLE global_priv EXTENDED;

-- Méthode 2: Optimisation (peut parfois corriger les index)
SELECT '=== Étape 4: Optimisation de la table ===' AS step;
OPTIMIZE TABLE global_priv;

-- Méthode 3: Vérification finale
SELECT '=== Étape 5: Vérification finale ===' AS step;
CHECK TABLE global_priv;

-- Afficher le nombre d'utilisateurs
SELECT '=== Étape 6: Comptage des utilisateurs ===' AS step;
SELECT COUNT(*) as total_users FROM global_priv;

-- Si tout a échoué, voir le fichier repair_mysql_global_priv.sql pour la recréation complète
