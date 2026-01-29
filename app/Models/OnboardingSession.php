<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnboardingSession extends Model
{
    protected $fillable = [
        'session_id',
        'hospital_name',
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
}
