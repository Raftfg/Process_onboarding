<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAdminAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // IMPORTANT: S'assurer qu'on reste sur la base principale
        $originalConnection = Config::get('database.default');
        if ($originalConnection !== 'mysql') {
            Config::set('database.default', 'mysql');
            DB::purge('tenant');
        }

        // Si l'admin est déjà authentifié, rediriger vers le dashboard admin
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }

        return $next($request);
    }
}
