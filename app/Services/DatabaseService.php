<?php

namespace App\Services;

use App\Models\AppDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class DatabaseService
{
    /**
     * Crée une base de données MySQL pour une application
     * 
     * @param int $applicationId ID de l'application
     * @param string $appName Nom de l'application (pour générer le nom de la DB)
     * @return array ['database' => AppDatabase, 'plain_password' => string]
     */
    public function createApplicationDatabase(int $applicationId, string $appName): array
    {
        // Générer un nom de base de données unique
        $databaseName = $this->generateUniqueDatabaseName($appName);
        
        // Générer des credentials sécurisés
        $dbUsername = $this->generateDatabaseUsername($appName);
        $dbPassword = $this->generateSecurePassword();
        $dbPasswordHash = Hash::make($dbPassword);

        // Créer la base de données MySQL
        $this->createMySQLDatabase($databaseName);

        // Créer l'utilisateur MySQL
        $this->createMySQLUser($dbUsername, $dbPassword, $databaseName);

        // Enregistrer dans la base centrale
        $appDatabase = AppDatabase::create([
            'application_id' => $applicationId,
            'database_name' => $databaseName,
            'db_username' => $dbUsername,
            'db_password' => $dbPasswordHash,
            'db_host' => config('database.connections.mysql.host', 'localhost'),
            'db_port' => config('database.connections.mysql.port', 3306),
            'status' => 'active',
        ]);

        Log::info('Base de données créée pour application', [
            'application_id' => $applicationId,
            'database_name' => $databaseName,
            'db_username' => $dbUsername,
        ]);

        return [
            'database' => $appDatabase,
            'plain_password' => $dbPassword, // À afficher une seule fois
        ];
    }

    /**
     * Génère un nom de base de données unique
     */
    protected function generateUniqueDatabaseName(string $appName): string
    {
        $prefix = config('app.database_prefix', 'app_');
        $baseName = $prefix . Str::slug($appName, '_');
        $databaseName = $baseName;
        $counter = 1;
        $maxAttempts = 100;

        // Vérifier l'unicité
        while ($this->databaseNameExists($databaseName) && $counter < $maxAttempts) {
            $databaseName = $baseName . '_' . $counter;
            $counter++;
        }

        // Si on a atteint le maximum, utiliser un timestamp
        if ($counter >= $maxAttempts) {
            $databaseName = $baseName . '_' . time();
        }

        return $databaseName;
    }

    /**
     * Vérifie si un nom de base de données existe déjà
     */
    protected function databaseNameExists(string $databaseName): bool
    {
        // Vérifier dans notre table
        if (AppDatabase::where('database_name', $databaseName)->exists()) {
            return true;
        }

        // Vérifier dans MySQL
        try {
            $rootUsername = config('database.connections.mysql.root_username', env('DB_ROOT_USERNAME', 'root'));
            $rootPassword = config('database.connections.mysql.root_password', env('DB_ROOT_PASSWORD', ''));
            
            $pdo = new \PDO(
                "mysql:host=" . config('database.connections.mysql.host'),
                $rootUsername,
                $rootPassword
            );
            
            $stmt = $pdo->query("SHOW DATABASES LIKE '{$databaseName}'");
            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            Log::error('Erreur lors de la vérification de la base de données: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Génère un nom d'utilisateur MySQL
     */
    protected function generateDatabaseUsername(string $appName): string
    {
        $baseUsername = 'app_' . Str::slug($appName, '_') . '_user';
        $username = $baseUsername;
        $counter = 1;
        $maxAttempts = 100;

        // Vérifier l'unicité (simplifié - on pourrait vérifier dans MySQL)
        while ($counter < $maxAttempts) {
            if (!AppDatabase::where('db_username', $username)->exists()) {
                break;
            }
            $username = $baseUsername . '_' . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * Génère un mot de passe sécurisé
     */
    protected function generateSecurePassword(int $length = 32): string
    {
        return Str::random($length);
    }

    /**
     * Crée une base de données MySQL
     */
    protected function createMySQLDatabase(string $databaseName): void
    {
        try {
            $rootUsername = config('database.connections.mysql.root_username', env('DB_ROOT_USERNAME', 'root'));
            $rootPassword = config('database.connections.mysql.root_password', env('DB_ROOT_PASSWORD', ''));

            $pdo = new \PDO(
                "mysql:host=" . config('database.connections.mysql.host'),
                $rootUsername,
                $rootPassword
            );

            // Créer la base de données
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            Log::info("Base de données MySQL créée: {$databaseName}");
        } catch (\PDOException $e) {
            Log::error("Erreur lors de la création de la base de données: " . $e->getMessage());
            throw new \Exception("Impossible de créer la base de données: " . $e->getMessage());
        }
    }

    /**
     * Crée un utilisateur MySQL et lui donne les droits sur la base
     */
    protected function createMySQLUser(string $username, string $password, string $databaseName): void
    {
        try {
            $rootUsername = config('database.connections.mysql.root_username', env('DB_ROOT_USERNAME', 'root'));
            $rootPassword = config('database.connections.mysql.root_password', env('DB_ROOT_PASSWORD', ''));
            $host = config('database.connections.mysql.host', 'localhost');

            $pdo = new \PDO(
                "mysql:host={$host}",
                $rootUsername,
                $rootPassword
            );

            // Créer l'utilisateur
            $pdo->exec("CREATE USER IF NOT EXISTS '{$username}'@'{$host}' IDENTIFIED BY '{$password}'");

            // Donner tous les droits sur la base de données
            $pdo->exec("GRANT ALL PRIVILEGES ON `{$databaseName}`.* TO '{$username}'@'{$host}'");

            // Appliquer les changements
            $pdo->exec("FLUSH PRIVILEGES");

            Log::info("Utilisateur MySQL créé: {$username} pour la base {$databaseName}");
        } catch (\PDOException $e) {
            Log::error("Erreur lors de la création de l'utilisateur MySQL: " . $e->getMessage());
            throw new \Exception("Impossible de créer l'utilisateur MySQL: " . $e->getMessage());
        }
    }

    /**
     * Retourne la chaîne de connexion complète
     */
    public function getConnectionString(AppDatabase $appDatabase, string $plainPassword): string
    {
        return sprintf(
            'mysql://%s:%s@%s:%d/%s',
            $appDatabase->db_username,
            $plainPassword,
            $appDatabase->db_host,
            $appDatabase->db_port,
            $appDatabase->database_name
        );
    }
}
