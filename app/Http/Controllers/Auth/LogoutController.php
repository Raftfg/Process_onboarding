<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogoutController extends Controller
{
    /**
     * Déconnecte l'utilisateur
     */
    public function __invoke(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Revenir à la base principale
        \Illuminate\Support\Facades\Config::set('database.default', 'mysql');
        \Illuminate\Support\Facades\DB::purge('tenant');
        session()->forget('current_subdomain');

        return redirect('/');
    }
}
