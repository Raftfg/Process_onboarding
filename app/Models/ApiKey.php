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
        'is_active',
        'expires_at',
        'last_used_at',
        'allowed_ips',
        'rate_limit',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'allowed_ips' => 'array',
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
            'is_active' => $options['is_active'] ?? true,
            'expires_at' => $options['expires_at'] ?? null,
            'allowed_ips' => $options['allowed_ips'] ?? null,
            'rate_limit' => $options['rate_limit'] ?? 100,
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
}
