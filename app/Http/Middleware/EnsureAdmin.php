<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class EnsureAdmin
{
    /**
     * Handle an incoming request.
     *
     * Pour l'instant, on vérifie via une variable d'environnement ADMIN_EMAIL
     * ou via une session admin. Plus tard, on pourra utiliser une vraie table d'admins.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // S'assurer qu'on utilise la base principale (pas de tenant)
        Config::set('database.default', 'mysql');
        DB::purge('tenant');

        // Vérifier si l'utilisateur est admin
        // Option 1: Via variable d'environnement (pour développement)
        $adminEmail = env('ADMIN_EMAIL');
        if ($adminEmail && $request->user() && $request->user()->email === $adminEmail) {
            return $next($request);
        }

        // Option 2: Via session admin (pour authentification simple)
        if (session('is_admin') === true) {
            return $next($request);
        }

        // Option 3: Vérifier si l'utilisateur a un rôle admin dans la base principale
        // (à implémenter si vous avez une table users dans la base principale avec un champ role)

        // Si aucune condition n'est remplie, rediriger vers la page de login admin
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Accès non autorisé'], 403);
        }

        return redirect()->route('admin.login')
            ->with('error', 'Vous devez être administrateur pour accéder à cette page.');
    }
}
