<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InformationPersonne extends Model
{
    use HasFactory;

    protected $table = 'information_personnes';

    protected $fillable = [
        'user_id',
        'prenom',
        'nom',
        'date_naissance',
        'sexe',
        'telephone',
        'adresse',
        'ville',
        'code_postal',
        'pays',
        'photo',
        'notes',
    ];

    protected $casts = [
        'date_naissance' => 'date',
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
     * Accessor pour le nom complet
     */
    public function getNomCompletAttribute(): string
    {
        return trim("{$this->prenom} {$this->nom}");
    }
}

