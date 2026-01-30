<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class AdminLoginController extends Controller
{
    /**
     * Affiche le formulaire de connexion admin
     */
    public function showLoginForm()
    {
        // S'assurer qu'on est sur la base principale
        $originalConnection = Config::get('database.default');
        if ($originalConnection !== 'mysql') {
            Config::set('database.default', 'mysql');
            DB::purge('tenant');
        }

        return view('admin.auth.login');
    }

    /**
     * Traite la tentative de connexion admin
     */
    public function login(Request $request)
    {
        // S'assurer qu'on est sur la base principale
        $originalConnection = Config::get('database.default');
        if ($originalConnection !== 'mysql') {
            Config::set('database.default', 'mysql');
            DB::purge('tenant');
        }

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Tenter la connexion avec le guard 'admin'
        if (Auth::guard('admin')->attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            $admin = Auth::guard('admin')->user();
            
            // Mettre à jour last_login_at
            if ($admin) {
                \App\Models\AdminUser::where('id', $admin->id)->update(['last_login_at' => now()]);
            }

            Log::info('Super-admin connecté: ' . $admin->email);

            return redirect()->intended(route('admin.dashboard'));
        }

        return back()->withErrors([
            'email' => 'Les identifiants fournis ne correspondent pas à nos enregistrements.',
        ])->onlyInput('email');
    }

    /**
     * Déconnexion admin
     */
    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
