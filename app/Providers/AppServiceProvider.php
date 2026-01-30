<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Charger le correctif pour Component si nécessaire
        if (!class_exists(\Illuminate\View\Component::class)) {
        }
    }

    public function boot(): void
    {
        //
    }
}
