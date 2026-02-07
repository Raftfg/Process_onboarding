<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class Application extends Model
{
    use HasFactory;
    protected $fillable = [
        'app_id',
        'app_name',
        'display_name',
        'contact_email',
        'website',
        'master_key',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = [
        'master_key', // Ne jamais exposer la master key
    ];

    /**
     * Génère une nouvelle application avec master key
     */
    public static function register(string $appName, string $displayName, string $contactEmail, ?string $website = null): array
    {
        // Générer un app_id unique
        $appId = 'app_' . Str::random(32);
        
        // Générer une master key sécurisée
        $masterKey = 'mk_' . Str::random(48);
        $masterKeyHash = Hash::make($masterKey);

        // Créer l'application
        $application = self::create([
            'app_id' => $appId,
            'app_name' => $appName,
            'display_name' => $displayName,
            'contact_email' => $contactEmail,
            'website' => $website,
            'master_key' => $masterKeyHash,
            'is_active' => true,
        ]);

        // Retourner l'application avec la master key en clair (affichée une seule fois)
        return [
            'id' => $application->id,
            'app_id' => $application->app_id,
            'app_name' => $application->app_name,
            'display_name' => $application->display_name,
            'contact_email' => $application->contact_email,
            'website' => $application->website,
            'master_key' => $masterKey, // Clé en clair (à sauvegarder immédiatement)
            'created_at' => $application->created_at,
        ];
    }

    /**
     * Valide une master key
     */
    public static function validateMasterKey(string $masterKey): ?self
    {
        // Extraire le préfixe si possible (mk_...)
        $prefix = substr($masterKey, 0, 3);
        
        // Trouver toutes les applications actives
        $applications = self::where('is_active', true)->get();

        foreach ($applications as $application) {
            // Vérifier le hash
            if (Hash::check($masterKey, $application->master_key)) {
                // Mettre à jour la dernière utilisation
                $application->update(['last_used_at' => now()]);
                return $application;
            }
        }

        return null;
    }

    /**
     * Vérifie si le nom d'application est disponible
     */
    public static function isAppNameAvailable(string $appName): bool
    {
        return !self::where('app_name', $appName)->exists();
    }

    /**
     * Relation avec les clés API
     */
    public function apiKeys()
    {
        return $this->hasMany(ApiKey::class);
    }

    /**
     * Relation avec la base de données de l'application
     */
    public function appDatabase()
    {
        return $this->hasOne(AppDatabase::class);
    }

    /**
     * Relation avec les enregistrements d'onboarding
     */
    public function onboardingRegistrations()
    {
        return $this->hasMany(OnboardingRegistration::class);
    }

    /**
     * Vérifie si l'application peut créer des clés API
     */
    public function canCreateApiKeys(): bool
    {
        return $this->is_active;
    }

    /**
     * Vérifie si l'application a une base de données configurée
     */
    public function hasDatabase(): bool
    {
        return $this->appDatabase !== null && $this->appDatabase->isActive();
    }
}
