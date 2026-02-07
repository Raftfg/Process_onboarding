<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class AppDatabase extends Model
{
    use HasFactory;
    protected $fillable = [
        'application_id',
        'database_name',
        'db_username',
        'db_password',
        'db_host',
        'db_port',
        'status',
    ];

    protected $casts = [
        'db_port' => 'integer',
    ];

    protected $hidden = [
        'db_password', // Ne jamais exposer le mot de passe
    ];

    /**
     * Relation avec l'application
     */
    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * Relation avec les enregistrements d'onboarding
     */
    public function onboardingRegistrations()
    {
        return $this->hasMany(OnboardingRegistration::class);
    }

    /**
     * Vérifie si la base de données est active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Retourne la chaîne de connexion (sans mot de passe)
     */
    public function getConnectionString(): string
    {
        return sprintf(
            'mysql://%s:***@%s:%d/%s',
            $this->db_username,
            $this->db_host,
            $this->db_port,
            $this->database_name
        );
    }

    /**
     * Retourne les credentials pour l'application (une seule fois)
     */
    public function getCredentialsForDisplay(): array
    {
        // Cette méthode ne devrait être appelée qu'une seule fois lors de la création
        // Le mot de passe en clair doit être stocké temporairement ailleurs
        return [
            'database_name' => $this->database_name,
            'host' => $this->db_host,
            'port' => $this->db_port,
            'username' => $this->db_username,
            // Le mot de passe en clair doit être passé séparément lors de la création
        ];
    }
}
