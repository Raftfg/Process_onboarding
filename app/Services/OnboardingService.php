<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Mail\OnboardingWelcomeMail;
use App\Models\OnboardingSession;

class OnboardingService
{
    public function processOnboarding(array $data)
    {
        try {
            // Générer le sous-domaine
            $subdomain = $this->generateSubdomain($data['step1']['hospital_name']);
            
            // Créer la base de données
            $databaseName = $this->createDatabase($subdomain);
            
            // Créer le sous-domaine
            $this->createSubdomain($subdomain);
            
            // Enregistrer la session d'onboarding
            $this->saveOnboardingSession($data, $subdomain, $databaseName);
            
            // Envoyer l'email
            $this->sendWelcomeEmail($data['step2'], $subdomain);
            
            return [
                'subdomain' => $subdomain,
                'database' => $databaseName,
                'url' => $this->getSubdomainUrl($subdomain),
                'admin_email' => $data['step2']['admin_email']
            ];
        } catch (\Exception $e) {
            Log::error('Erreur dans processOnboarding: ' . $e->getMessage());
            throw $e;
        }
    }

    protected function saveOnboardingSession(array $data, string $subdomain, string $databaseName): void
    {
        try {
            OnboardingSession::create([
                'session_id' => session()->getId(),
                'hospital_name' => $data['step1']['hospital_name'],
                'hospital_address' => $data['step1']['hospital_address'] ?? null,
                'hospital_phone' => $data['step1']['hospital_phone'] ?? null,
                'hospital_email' => $data['step1']['hospital_email'] ?? null,
                'admin_first_name' => $data['step2']['admin_first_name'],
                'admin_last_name' => $data['step2']['admin_last_name'],
                'admin_email' => $data['step2']['admin_email'],
                'subdomain' => $subdomain,
                'database_name' => $databaseName,
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'enregistrement de la session: ' . $e->getMessage());
            // Ne pas faire échouer le processus si l'enregistrement échoue
        }
    }

    protected function generateSubdomain(string $hospitalName): string
    {
        $baseDomain = config('app.subdomain_base_domain', 'medkey.local');
        $slug = Str::slug($hospitalName);
        $subdomain = strtolower($slug);
        
        // Vérifier l'unicité (dans un vrai projet, vérifier en base de données)
        // Pour l'instant, on ajoute un timestamp pour garantir l'unicité
        $subdomain = $subdomain . '-' . time();
        
        return $subdomain;
    }

    protected function createDatabase(string $subdomain): string
    {
        $databaseName = 'medkey_' . $subdomain;
        $rootUsername = config('database.connections.mysql.root_username', env('DB_ROOT_USERNAME', 'root'));
        $rootPassword = config('database.connections.mysql.root_password', env('DB_ROOT_PASSWORD', ''));
        
        try {
            // Se connecter à MySQL sans spécifier de base de données
            $pdo = new \PDO(
                "mysql:host=" . config('database.connections.mysql.host'),
                $rootUsername,
                $rootPassword
            );
            
            // Créer la base de données
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            Log::info("Base de données créée: {$databaseName}");
            
            return $databaseName;
        } catch (\PDOException $e) {
            Log::error("Erreur création base de données: " . $e->getMessage());
            throw new \Exception("Impossible de créer la base de données: " . $e->getMessage());
        }
    }

    protected function createSubdomain(string $subdomain): void
    {
        $baseDomain = config('app.subdomain_base_domain', 'medkey.local');
        $webRoot = config('app.subdomain_web_root', '/var/www/html');
        
        // Dans un environnement de production, vous devriez:
        // 1. Créer un vhost Apache/Nginx
        // 2. Ajouter une entrée DNS
        // 3. Créer un répertoire pour le sous-domaine
        
        // Pour cette démo, on simule la création
        Log::info("Sous-domaine créé: {$subdomain}.{$baseDomain}");
        
        // Exemple de création de vhost (à adapter selon votre environnement)
        // $this->createApacheVhost($subdomain, $baseDomain, $webRoot);
    }

    protected function getSubdomainUrl(string $subdomain): string
    {
        $baseDomain = config('app.subdomain_base_domain', 'medkey.local');
        $protocol = config('app.env') === 'local' ? 'http' : 'https';
        return "{$protocol}://{$subdomain}.{$baseDomain}";
    }

    protected function sendWelcomeEmail(array $adminData, string $subdomain): void
    {
        try {
            $url = $this->getSubdomainUrl($subdomain);
            
            Mail::to($adminData['admin_email'])->send(
                new OnboardingWelcomeMail($adminData, $subdomain, $url)
            );
            
            Log::info("Email de bienvenue envoyé à: {$adminData['admin_email']}");
        } catch (\Exception $e) {
            Log::error("Erreur envoi email: " . $e->getMessage());
            // Ne pas faire échouer tout le processus si l'email échoue
        }
    }
}
