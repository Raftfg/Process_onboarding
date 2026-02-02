<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnboardingSession extends Model
{
    protected $fillable = [
        'session_id',
        'organization_name',
        'slug',
        'organization_address',
        'organization_phone',
        'organization_email',
        'admin_first_name',
        'admin_last_name',
        'admin_email',
        'subdomain',
        'database_name',
        'status',
        'metadata',
        'completed_at',
        'source_app_name',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Scope pour les tenants actifs
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'completed')
                    ->whereNotNull('database_name');
    }

    /**
     * Scope pour les tenants inactifs
     */
    public function scopeInactive($query)
    {
        return $query->where('status', 'completed')
                    ->whereNull('database_name');
    }

    /**
     * Scope pour les tenants complétés
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}
