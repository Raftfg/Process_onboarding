<?php

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;

return [
    'name' => env('APP_NAME', 'Akasi Group'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => env('APP_TIMEZONE', 'UTC'),
    'locale' => env('APP_LOCALE', 'fr'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'fr'),
    'faker_locale' => env('APP_FAKER_LOCALE', 'fr_FR'),
    'key' => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',
    'subdomain_base_domain' => env('SUBDOMAIN_BASE_DOMAIN', 'akasigroup.local'),
    'subdomain_web_root' => env('SUBDOMAIN_WEB_ROOT', '/var/www/html'),
    'activation_token_expires_days' => env('ACTIVATION_TOKEN_EXPIRES_DAYS', 7), // Durée de validité du token d'activation en jours

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

    'providers' => ServiceProvider::defaultProviders()->merge([
        App\Providers\AppServiceProvider::class,
        App\Providers\RouteServiceProvider::class,
    ])->toArray(),

    'aliases' => Facade::defaultAliases()->merge([
        // 
    ])->toArray(),
];
