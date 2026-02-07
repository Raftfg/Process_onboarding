<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OnboardingRegistration extends Model
{
    use HasFactory;
    protected $fillable = [
        'uuid',
        'application_id',
        'app_database_id',
        'email',
        'organization_name',
        'subdomain',
        'status',
        'api_key',
        'api_secret',
        'metadata',
        'dns_configured',
        'ssl_configured',
        'provisioning_attempts',
        'completed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'dns_configured' => 'boolean',
        'ssl_configured' => 'boolean',
        'completed_at' => 'datetime',
    ];

    protected $hidden = [
        'api_secret', // Ne jamais exposer le secret
    ];

    /**
     * Boot du modèle - Génère UUID automatiquement
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Relation avec l'application
     */
    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * Relation avec la base de données de l'application
     */
    public function appDatabase()
    {
        return $this->belongsTo(AppDatabase::class);
    }

    /**
     * Vérifie si l'onboarding est en attente
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Vérifie si l'onboarding est activé
     */
    public function isActivated(): bool
    {
        return $this->status === 'activated';
    }

    /**
     * Vérifie si l'onboarding est annulé
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Vérifie si l'onboarding est complété
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Vérifie si l'infrastructure est complètement configurée
     */
    public function isInfrastructureReady(): bool
    {
        return $this->dns_configured && $this->ssl_configured;
    }
}
