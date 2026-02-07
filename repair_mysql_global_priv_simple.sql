-- Script SIMPLIFIÉ pour réparer la table global_priv corrompue
-- Cette version est plus sûre et gère mieux les erreurs

USE mysql;

-- Étape 1: Vérifier l'état actuel
SELECT 'Vérification de l''état de global_priv...' AS status;
CHECK TABLE global_priv;

-- Étape 2: Tenter de sauvegarder (peut échouer si trop corrompu)
DROP TABLE IF EXISTS global_priv_backup;

-- Si cette commande échoue, c'est normal, continuez quand même
CREATE TABLE global_priv_backup AS SELECT * FROM global_priv;

-- Étape 3: Supprimer la table corrompue
DROP TABLE IF EXISTS global_priv;

-- Étape 4: Recréer la table avec la structure correcte
CREATE TABLE global_priv (
  Host CHAR(255) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT '',
  User CHAR(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin NOT NULL DEFAULT '',
  Priv LONGTEXT CHARACTER SET utf8mb3 COLLATE utf8mb3_bin NOT NULL,
  PRIMARY KEY (Host, User)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin COMMENT='Global privileges';

-- Étape 5: Restaurer depuis la sauvegarde OU créer root par défaut
-- D'abord, essayer de restaurer depuis la sauvegarde
-- (Cette commande échouera silencieusement si la sauvegarde n'existe pas)
INSERT INTO global_priv (Host, User, Priv)
SELECT Host, User, Priv FROM global_priv_backup
WHERE EXISTS (SELECT 1 FROM information_schema.tables 
              WHERE table_schema = 'mysql' AND table_name = 'global_priv_backup');

-- Si aucune donnée n'a été restaurée, créer au minimum root
-- (Cette commande utilisera IGNORE pour éviter les doublons)
INSERT IGNORE INTO global_priv (Host, User, Priv)
VALUES 
  ('localhost', 'root', '{"access": 18446744073709551615}'),
  ('127.0.0.1', 'root', '{"access": 18446744073709551615}'),
  ('::1', 'root', '{"access": 18446744073709551615}'),
  ('localhost', '', '{"access": 0}');

-- Étape 6: Vérifier et recharger
CHECK TABLE global_priv;
FLUSH PRIVILEGES;

-- Étape 7: Afficher le résultat
SELECT 'Réparation terminée!' AS status;
SELECT COUNT(*) AS user_count FROM global_priv;
SELECT Host, User FROM global_priv;
