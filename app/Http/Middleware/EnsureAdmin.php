<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // IMPORTANT: S'assurer qu'on reste sur la base principale
        // Ne jamais basculer vers les bases tenant pour les routes admin
        $originalConnection = Config::get('database.default');
        if ($originalConnection !== 'mysql') {
            Config::set('database.default', 'mysql');
            DB::purge('tenant');
        }

        // Vérifier l'authentification avec le guard 'admin'
        if (!Auth::guard('admin')->check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('admin.login');
        }

        // Vérifier que l'admin est actif
        $admin = Auth::guard('admin')->user();
        if (!$admin->isActive()) {
            Auth::guard('admin')->logout();
            return redirect()->route('admin.login')->with('error', 'Votre compte a été désactivé.');
        }

        return $next($request);
    }
}
