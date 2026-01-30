<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfigurationDashboard extends Model
{
    use HasFactory;

    protected $table = 'configuration_dashboard';

    protected $fillable = [
        'user_id',
        'theme',
        'langue',
        'widgets_config',
        'preferences',
    ];

    protected $casts = [
        'widgets_config' => 'array',
        'preferences' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relation avec User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Récupère ou crée la configuration pour un utilisateur
     */
    public static function getOrCreateForUser(int $userId): self
    {
        return static::firstOrCreate(
            ['user_id' => $userId],
            [
                'theme' => 'light',
                'langue' => 'fr',
                'widgets_config' => [],
                'preferences' => [],
            ]
        );
    }
}

