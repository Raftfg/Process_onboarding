-- Script de réparation de la table global_priv corrompue
-- ATTENTION: Ce script manipule une table système MySQL critique
-- Exécutez-le avec précaution et sauvegardez d'abord votre base de données

-- Étape 1: Vérifier l'état actuel
USE mysql;
SHOW TABLES LIKE 'global_priv';

-- Étape 2: Tenter de réparer d'abord (si possible)
-- Si MySQL le permet, cette commande peut réparer sans perte de données
CHECK TABLE global_priv;
REPAIR TABLE global_priv;

-- Si la réparation échoue, procéder à la recréation
-- Étape 3: Sauvegarder les données importantes (si possible)
-- Créer une table temporaire pour sauvegarder les données
CREATE TABLE IF NOT EXISTS global_priv_backup AS 
SELECT * FROM global_priv WHERE 1=0; -- Structure seulement

-- Tenter de copier les données (peut échouer si la table est trop corrompue)
INSERT INTO global_priv_backup 
SELECT * FROM global_priv;

-- Étape 4: Supprimer la table corrompue
DROP TABLE IF EXISTS global_priv;

-- Étape 5: Recréer la table avec la structure par défaut MySQL 8.0+
-- Structure standard de global_priv dans MySQL 8.0+
CREATE TABLE global_priv (
  Host char(255) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT '',
  User char(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin NOT NULL DEFAULT '',
  Priv set('Select','Insert','Update','Delete','Create','Drop','Reload','Shutdown','Process','File','Grant','References','Index','Alter','Show_db','Super','Create_tmp_table','Lock_tables','Execute','Repl_slave','Repl_client','Create_view','Show_view','Create_routine','Alter_routine','Create_user','Event','Trigger','Create_tablespace','Create_role','Drop_role') CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  PRIMARY KEY (Host,User)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin COMMENT='Global privileges';

-- Étape 6: Restaurer les données depuis la sauvegarde (si disponible)
-- Si la sauvegarde a réussi, restaurer les données
INSERT INTO global_priv 
SELECT * FROM global_priv_backup;

-- Étape 7: Nettoyer
-- Garder la sauvegarde temporairement pour vérification
-- DROP TABLE IF EXISTS global_priv_backup; -- Décommentez après vérification

-- Étape 8: Vérifier que tout fonctionne
CHECK TABLE global_priv;
SELECT COUNT(*) as user_count FROM global_priv;

-- Étape 9: Recharger les privilèges
FLUSH PRIVILEGES;
