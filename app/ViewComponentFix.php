<?php

// Workaround pour Laravel 10 - la classe Component n'existe pas dans cette version
// Ce fichier fournit une classe factice pour éviter l'erreur dans ViewServiceProvider

namespace Illuminate\View;

class Component
{
    /**
     * Flush the component's cache.
     *
     * @return void
     */
    public static function flushCache(): void
    {
        // Ne rien faire - cette méthode n'est pas nécessaire dans Laravel 10
    }
}
