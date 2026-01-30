<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Webhook extends Model
{
    protected $fillable = [
        'url',
        'events',
        'is_active',
        'secret',
        'api_key_id',
    ];

    protected $casts = [
        'events' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Relation avec ApiKey
     */
    public function apiKey()
    {
        return $this->belongsTo(ApiKey::class);
    }

    /**
     * Vérifie si le webhook doit être déclenché pour un événement
     */
    public function shouldTrigger(string $event): bool
    {
        if (!$this->is_active) {
            return false;
        }

        return in_array($event, $this->events ?? []);
    }

    /**
     * Génère une signature pour le webhook
     */
    public function generateSignature(array $payload): string
    {
        $payloadString = json_encode($payload);
        return hash_hmac('sha256', $payloadString, $this->secret);
    }
}
