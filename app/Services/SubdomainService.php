<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SubdomainService
{
    /**
     * Sous-domaines réservés qui ne peuvent pas être utilisés
     */
    private const RESERVED_SUBDOMAINS = [
        'www', 'mail', 'ftp', 'admin', 'api', 'app', 'test', 'dev', 'staging',
        'prod', 'localhost', 'www2', 'ns1', 'ns2', 'mx', 'smtp', 'pop', 'imap',
    ];

    /**
     * Active la vérification DNS (peut être désactivée pour performance)
     */
    private bool $enableDnsCheck = false;

    /**
     * Génère un sous-domaine unique basé sur l'organisation ou l'email
     * 
     * Utilise une validation hybride : DB + DNS optionnel
     */
    public function generateUniqueSubdomain(?string $organizationName = null, string $email = '', bool $checkDns = false): string
    {
        // Si organization_name est fourni, l'utiliser comme base
        if (!empty($organizationName)) {
            $baseSubdomain = Str::slug($organizationName, '-', 'fr');
        } else {
            // Sinon, utiliser la partie locale de l'email
            $emailLocal = explode('@', $email)[0];
            $baseSubdomain = Str::slug($emailLocal, '-', 'fr');
        }

        // Valider et nettoyer le format
        $baseSubdomain = $this->sanitizeSubdomain($baseSubdomain);

        // Si vide, utiliser un nom par défaut
        if (empty($baseSubdomain)) {
            $baseSubdomain = 'client';
        }

        // Vérifier si c'est un sous-domaine réservé
        if (in_array(strtolower($baseSubdomain), self::RESERVED_SUBDOMAINS)) {
            $baseSubdomain = $baseSubdomain . '-1';
        }

        // Générer un sous-domaine unique avec retry automatique
        $subdomain = $this->findAvailableSubdomain($baseSubdomain, $checkDns);

        return $subdomain;
    }

    /**
     * Trouve un sous-domaine disponible avec retry automatique
     */
    private function findAvailableSubdomain(string $baseSubdomain, bool $checkDns = false): string
    {
        $subdomain = $baseSubdomain;
        $counter = 1;
        $maxAttempts = 100;

        while ($counter < $maxAttempts) {
            // Vérifier la disponibilité
            $check = $this->checkSubdomainAvailability($subdomain, $checkDns);
            
            if ($check['available']) {
                return $subdomain;
            }

            // Gérer les conflits
            if ($check['conflict']) {
                Log::warning('Conflit de sous-domaine détecté', [
                    'subdomain' => $subdomain,
                    'exists_in_db' => $check['exists_in_db'],
                    'exists_in_dns' => $check['exists_in_dns'],
                ]);
            }

            // Essayer avec un suffixe numérique
            $subdomain = $baseSubdomain . '-' . $counter;
            $counter++;
        }

        // Si on a atteint le maximum, utiliser un timestamp
        Log::warning('Maximum de tentatives atteint pour génération de sous-domaine', [
            'base' => $baseSubdomain,
        ]);
        
        return $baseSubdomain . '-' . time();
    }

    /**
     * Vérifie la disponibilité d'un sous-domaine (validation hybride)
     * 
     * @return array ['available' => bool, 'exists_in_db' => bool, 'exists_in_dns' => bool, 'conflict' => bool]
     */
    public function checkSubdomainAvailability(string $subdomain, bool $checkDns = false): array
    {
        // 1. Valider le format
        if (!$this->isValidSubdomain($subdomain)) {
            return [
                'available' => false,
                'exists_in_db' => false,
                'exists_in_dns' => false,
                'conflict' => false,
                'reason' => 'invalid_format',
            ];
        }

        // 2. Vérifier en base de données (rapide)
        $existsInDb = $this->subdomainExistsInDatabase($subdomain);

        // 3. Vérifier DNS si demandé (plus lent mais plus fiable)
        $existsInDns = false;
        if ($checkDns) {
            $existsInDns = $this->subdomainExistsInDns($subdomain);
        }

        // 4. Détecter les conflits (existe en DB mais pas en DNS ou vice versa)
        $conflict = false;
        if ($existsInDb && $checkDns && !$existsInDns) {
            $conflict = true;
            Log::warning('Conflit: sous-domaine existe en DB mais pas en DNS', [
                'subdomain' => $subdomain,
            ]);
        } elseif (!$existsInDb && $checkDns && $existsInDns) {
            $conflict = true;
            Log::warning('Conflit: sous-domaine existe en DNS mais pas en DB', [
                'subdomain' => $subdomain,
            ]);
        }

        // Disponible si n'existe ni en DB ni en DNS
        $available = !$existsInDb && !$existsInDns;

        return [
            'available' => $available,
            'exists_in_db' => $existsInDb,
            'exists_in_dns' => $existsInDns,
            'conflict' => $conflict,
        ];
    }

    /**
     * Vérifie si un sous-domaine existe en base de données
     */
    protected function subdomainExists(string $subdomain): bool
    {
        return $this->subdomainExistsInDatabase($subdomain);
    }

    /**
     * Vérifie si un sous-domaine existe en base de données
     */
    private function subdomainExistsInDatabase(string $subdomain): bool
    {
        return \App\Models\OnboardingRegistration::where('subdomain', $subdomain)->exists();
    }

    /**
     * Vérifie si un sous-domaine existe réellement en DNS (résolution DNS)
     */
    private function subdomainExistsInDns(string $subdomain): bool
    {
        try {
            $baseDomain = config('app.brand_domain', 'akasigroup.local');
            $fullDomain = "{$subdomain}.{$baseDomain}";
            
            // Tenter de résoudre le domaine
            $ip = gethostbyname($fullDomain);
            
            // Si l'IP retournée est différente du domaine, c'est qu'il existe
            $exists = $ip !== $fullDomain && filter_var($ip, FILTER_VALIDATE_IP) !== false;
            
            if ($exists) {
                Log::info("Sous-domaine résolu en DNS", [
                    'subdomain' => $subdomain,
                    'full_domain' => $fullDomain,
                    'ip' => $ip,
                ]);
            }
            
            return $exists;
        } catch (\Exception $e) {
            // En cas d'erreur DNS, on considère qu'il n'existe pas (fail-safe)
            Log::debug("Erreur lors de la vérification DNS pour {$subdomain}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Nettoie et valide un sous-domaine
     */
    private function sanitizeSubdomain(string $subdomain): string
    {
        // Convertir en minuscules
        $subdomain = strtolower($subdomain);
        
        // Remplacer les caractères non autorisés par des tirets
        $subdomain = preg_replace('/[^a-z0-9-]/', '-', $subdomain);
        
        // Supprimer les tirets multiples
        $subdomain = preg_replace('/-+/', '-', $subdomain);
        
        // Supprimer les tirets en début et fin
        $subdomain = trim($subdomain, '-');
        
        // Limiter la longueur (max 63 caractères pour un sous-domaine DNS)
        $subdomain = substr($subdomain, 0, 63);
        
        return $subdomain;
    }

    /**
     * Configure le DNS pour un sous-domaine
     * 
     * Note: Cette méthode peut être étendue pour intégrer avec des APIs DNS
     * (Cloudflare, AWS Route53, etc.) ou simplement retourner true si configuré manuellement
     */
    public function configureDNS(string $subdomain): bool
    {
        try {
            // TODO: Intégrer avec API DNS (Cloudflare, AWS Route53, etc.)
            // Pour l'instant, on considère que c'est configuré manuellement ou via script
            
            Log::info("DNS configuré pour sous-domaine: {$subdomain}");
            
            // Retourner true si on suppose que c'est configuré
            // Dans un environnement réel, on appellerait l'API DNS ici
            return true;
        } catch (\Exception $e) {
            Log::error("Erreur lors de la configuration DNS: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Configure le SSL pour un sous-domaine
     * 
     * Note: Cette méthode peut être étendue pour intégrer avec Let's Encrypt
     * ou d'autres services SSL
     */
    public function configureSSL(string $subdomain): bool
    {
        try {
            // TODO: Intégrer avec Let's Encrypt ou autre service SSL
            // Pour l'instant, on considère que c'est configuré manuellement ou via script
            
            Log::info("SSL configuré pour sous-domaine: {$subdomain}");
            
            // Retourner true si on suppose que c'est configuré
            // Dans un environnement réel, on appellerait l'API SSL ici
            return true;
        } catch (\Exception $e) {
            Log::error("Erreur lors de la configuration SSL: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Génère l'URL complète du sous-domaine
     */
    public function getSubdomainUrl(string $subdomain): string
    {
        $baseDomain = config('app.brand_domain', 'akasigroup.local');
        $protocol = config('app.https', false) ? 'https' : 'http';
        
        return "{$protocol}://{$subdomain}.{$baseDomain}";
    }

    /**
     * Valide qu'un sous-domaine est valide (format)
     */
    public function isValidSubdomain(string $subdomain): bool
    {
        // Un sous-domaine doit :
        // - Être en minuscules
        // - Contenir uniquement lettres, chiffres et tirets
        // - Ne pas commencer ou finir par un tiret
        // - Faire entre 1 et 63 caractères (limite DNS)
        // - Ne pas être un sous-domaine réservé
        
        if (strlen($subdomain) < 1 || strlen($subdomain) > 63) {
            return false;
        }

        if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', $subdomain)) {
            return false;
        }

        // Vérifier si c'est un sous-domaine réservé
        if (in_array(strtolower($subdomain), self::RESERVED_SUBDOMAINS)) {
            return false;
        }

        return true;
    }

    /**
     * Active ou désactive la vérification DNS
     */
    public function setDnsCheckEnabled(bool $enabled): void
    {
        $this->enableDnsCheck = $enabled;
    }
}
