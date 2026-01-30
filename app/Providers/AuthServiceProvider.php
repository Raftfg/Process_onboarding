<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Configurer le modèle d'authentification dynamiquement selon le contexte
        // Cette méthode est appelée à chaque requête, donc on peut vérifier le sous-domaine
        $request = request();
        
        if ($request) {
            $subdomain = null;
            
            // Essayer d'extraire le sous-domaine depuis le host
            $host = $request->getHost();
            $parts = explode('.', $host);
            
            if (count($parts) >= 2 && $parts[1] === 'localhost') {
                $subdomain = $parts[0];
            } elseif (count($parts) >= 3) {
                $subdomain = $parts[0];
            }
            
            // Si on a un sous-domaine, utiliser le modèle Tenant\User
            if ($subdomain || session()->has('current_subdomain')) {
                Config::set('auth.providers.users.model', \App\Models\Tenant\User::class);
            }
        }
    }
}

