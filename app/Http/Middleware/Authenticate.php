<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson()) {
            return null;
        }

        // Ajouter le sous-domaine à l'URL de login si disponible
        $loginUrl = route('login');
        if (config('app.env') === 'local' && $request->has('subdomain')) {
            $loginUrl .= (strpos($loginUrl, '?') !== false ? '&' : '?') . 'subdomain=' . $request->get('subdomain');
        } elseif (session('current_subdomain')) {
            $loginUrl .= (strpos($loginUrl, '?') !== false ? '&' : '?') . 'subdomain=' . session('current_subdomain');
        }

        return $loginUrl;
    }
}
