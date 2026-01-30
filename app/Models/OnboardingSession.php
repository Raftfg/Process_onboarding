<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnboardingSession extends Model
{
    protected $fillable = [
        'session_id',
        'hospital_name',
        'slug',
        'hospital_address',
        'hospital_phone',
        'hospital_email',
        'admin_first_name',
        'admin_last_name',
        'admin_email',
        'subdomain',
        'database_name',
        'status',
        'completed_at'
    ];

    protected $casts = [
        'completed_at' => 'datetime',
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
