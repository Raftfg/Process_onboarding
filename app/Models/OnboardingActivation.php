<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class OnboardingActivation extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'organization_name',
        'token',
        'subdomain',
        'database_name',
        'expires_at',
        'activated_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'activated_at' => 'datetime',
    ];

    /**
     * Vérifie si le token est valide (non expiré et non activé)
     */
    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->isActivated();
    }

    /**
     * Vérifie si le token est expiré
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Vérifie si le token a déjà été activé
     */
    public function isActivated(): bool
    {
        return $this->activated_at !== null;
    }

    /**
     * Marque le token comme activé
     */
    public function markAsActivated(): void
    {
        $this->update(['activated_at' => now()]);
    }
}
