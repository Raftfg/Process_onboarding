<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class ApiKey extends Model
{
    protected $fillable = [
        'name',
        'key',
        'key_prefix',
        'app_name', // Nom de l'application technique (ex: Ejustice)
        'application_id', // ID de l'application propriétaire (si créée via self-service)
        'is_active',
        'expires_at',
        'last_used_at',
        'allowed_ips',
        'rate_limit',
        'api_config', // Configuration flexible de l'API
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'allowed_ips' => 'array',
        'api_config' => 'array',
    ];

    protected $hidden = [
        'key', // Ne jamais exposer la clé complète
    ];

    /**
     * Génère une nouvelle clé API
     */
    public static function generate(string $name, array $options = []): array
    {
        // Générer une clé aléatoire sécurisée
        $key = 'ak_' . Str::random(48);
        $keyPrefix = substr($key, 0, 8);

        // Hasher la clé pour le stockage
        $hashedKey = Hash::make($key);

        // Créer l'enregistrement
        $apiKey = self::create([
            'name' => $name,
            'key' => $hashedKey,
            'key_prefix' => $keyPrefix,
            'app_name' => $options['app_name'] ?? null,
            'application_id' => $options['application_id'] ?? null,
            'is_active' => $options['is_active'] ?? true,
            'expires_at' => $options['expires_at'] ?? null,
            'allowed_ips' => $options['allowed_ips'] ?? null,
            'rate_limit' => $options['rate_limit'] ?? 100,
            'api_config' => $options['api_config'] ?? null,
        ]);

        // Retourner la clé en clair (à afficher une seule fois)
        return [
            'id' => $apiKey->id,
            'key' => $key, // Clé en clair (à sauvegarder immédiatement)
            'key_prefix' => $keyPrefix,
            'name' => $apiKey->name,
            'app_name' => $apiKey->app_name,
        ];
    }

    /**
     * Vérifie si une clé API est valide
     */
    public static function validate(string $apiKey): ?self
    {
        // Extraire le préfixe
        $prefix = substr($apiKey, 0, 8);

        // Trouver toutes les clés avec ce préfixe
        $candidates = self::where('key_prefix', $prefix)
            ->where('is_active', true)
            ->get();

        foreach ($candidates as $candidate) {
            // Vérifier le hash
            if (Hash::check($apiKey, $candidate->key)) {
                // Vérifier l'expiration
                if ($candidate->expires_at && $candidate->expires_at->isPast()) {
                    continue;
                }

                // Mettre à jour la dernière utilisation
                $candidate->update(['last_used_at' => now()]);

                return $candidate;
            }
        }

        return null;
    }

    /**
     * Vérifie si l'IP est autorisée
     */
    public function isIpAllowed(string $ip): bool
    {
        if (!$this->allowed_ips || empty($this->allowed_ips)) {
            return true; // Toutes les IPs sont autorisées
        }

        return in_array($ip, $this->allowed_ips);
    }

    /**
     * Retourne les règles de validation selon la configuration de l'API
     * 
     * @return array Règles de validation Laravel
     */
    public function getValidationRules(): array
    {
        $config = $this->api_config ?? [];
        $rules = [];

        // Règle pour organization_name selon la config
        if ($this->shouldRequireOrganizationName()) {
            $rules['organization_name'] = 'required|string|max:255';
        } else {
            $rules['organization_name'] = 'nullable|string|max:255';
        }

        // Règles personnalisées depuis la config
        if (isset($config['custom_validation_rules']) && is_array($config['custom_validation_rules'])) {
            $rules = array_merge($rules, $config['custom_validation_rules']);
        }

        return $rules;
    }

    /**
     * Vérifie si organization_name est requis selon la configuration
     * 
     * @return bool True si requis, false sinon (défaut: true pour rétrocompatibilité)
     */
    public function shouldRequireOrganizationName(): bool
    {
        $config = $this->api_config ?? [];
        
        // Par défaut, on garde le comportement actuel (requis) pour rétrocompatibilité
        // Si la config existe et définit explicitement require_organization_name, on l'utilise
        if (isset($config['require_organization_name'])) {
            return (bool) $config['require_organization_name'];
        }

        // Par défaut, organization_name est requis (rétrocompatibilité)
        return true;
    }

    /**
     * Retourne la stratégie de génération du nom d'organisation
     * 
     * @return string Stratégie: 'auto', 'email', 'metadata', 'custom', ou null
     */
    public function getOrganizationNameGenerationStrategy(): ?string
    {
        $config = $this->api_config ?? [];
        
        return $config['organization_name_generation_strategy'] ?? 'auto';
    }

    /**
     * Retourne le template personnalisé pour la génération du nom d'organisation
     * 
     * @return string|null Template (ex: "Tenant-{timestamp}") ou null
     */
    public function getOrganizationNameTemplate(): ?string
    {
        $config = $this->api_config ?? [];
        
        return $config['organization_name_template'] ?? null;
    }

    /**
     * Retourne la configuration par défaut pour une nouvelle clé API
     * 
     * @return array Configuration par défaut
     */
    public static function getDefaultApiConfig(): array
    {
        return [
            'require_organization_name' => true, // Rétrocompatibilité
            'organization_name_generation_strategy' => 'auto',
            'organization_name_template' => null,
            'custom_validation_rules' => [],
        ];
    }
}
