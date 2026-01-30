<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            $user = auth()->user();
            
            // Si l'utilisateur n'a jamais changé son mot de passe, le forcer à le changer
            if ($user->password_changed_at === null) {
                // Exclure les routes de changement de mot de passe et de déconnexion
                if (!$request->is('change-password') && !$request->is('change-password/*') && !$request->is('logout')) {
                    return redirect()->route('password.change');
                }
            }
        }

        return $next($request);
    }
}
