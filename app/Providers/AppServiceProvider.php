<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

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
        // Partager les variables de branding avec toutes les vues
        View::share('brandName', config('app.brand_name'));
        View::share('brandDomain', config('app.brand_domain'));
    }
}
