<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    /**
     * Affiche le formulaire de connexion admin
     */
    public function showLoginForm()
    {
        // S'assurer qu'on utilise la base principale
        Config::set('database.default', 'mysql');
        DB::purge('tenant');

        return view('admin.auth.login');
    }

    /**
     * Traite la connexion admin
     */
    public function login(Request $request)
    {
        // S'assurer qu'on utilise la base principale
        Config::set('database.default', 'mysql');
        DB::purge('tenant');

        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Vérifier via variable d'environnement (pour développement)
        $adminEmail = env('ADMIN_EMAIL');
        $adminPassword = env('ADMIN_PASSWORD');

        if ($adminEmail && $adminPassword) {
            if ($request->email === $adminEmail && $request->password === $adminPassword) {
                // Créer une session admin
                session(['is_admin' => true, 'admin_email' => $request->email]);
                
                Log::info("Connexion admin réussie", ['email' => $request->email]);
                
                return redirect()->route('admin.dashboard');
            }
        }

        // Essayer avec la table users de la base principale si elle existe
        $user = \App\Models\User::where('email', $request->email)->first();
        
        if ($user && Hash::check($request->password, $user->password)) {
            // Vérifier si c'est un admin (via variable d'environnement ou champ role si disponible)
            if ($user->email === env('ADMIN_EMAIL') || (isset($user->role) && $user->role === 'admin')) {
                Auth::login($user);
                session(['is_admin' => true, 'admin_email' => $request->email]);
                
                Log::info("Connexion admin réussie", ['email' => $request->email]);
                
                return redirect()->route('admin.dashboard');
            }
        }

        return back()->withErrors([
            'email' => 'Les identifiants fournis ne correspondent pas à un administrateur.',
        ])->onlyInput('email');
    }

    /**
     * Déconnexion admin
     */
    public function logout()
    {
        session()->forget(['is_admin', 'admin_email']);
        Auth::logout();
        
        Log::info("Déconnexion admin");
        
        return redirect()->route('admin.login');
    }
}

