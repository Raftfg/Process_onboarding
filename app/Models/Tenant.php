<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * La table tenants existe uniquement dans la base principale
     * 
     * @var string
     */
    protected $connection = 'mysql';

    protected $fillable = [
        'subdomain',
        'database_name',
        'name',
        'email',
        'phone',
        'address',
        'status',
        'plan',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Vérifie si le tenant est actif
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Relation avec OnboardingSession (optionnel, pour la migration)
     */
    public function onboardingSession()
    {
        return $this->hasOne(OnboardingSession::class, 'subdomain', 'subdomain');
    }
}
