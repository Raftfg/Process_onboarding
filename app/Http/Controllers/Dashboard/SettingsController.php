<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class SettingsController extends Controller
{
    /**
     * Afficher les paramètres
     */
    public function index()
    {
        $user = Auth::user();
        
        if (!$user) {
            // Si l'utilisateur n'est pas authentifié, rediriger vers login
            return redirect()->route('login');
        }
        
        return view('dashboard.settings.index', compact('user'));
    }

    /**
     * Mettre à jour les paramètres
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        $user->name = $request->name;
        $user->email = $request->email;
        $user->phone = $request->phone;

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        // Préserver le token auto_login_token dans la redirection si présent
        $redirect = redirect()->route('dashboard.settings')
            ->with('success', 'Paramètres mis à jour avec succès');
        
        if (request()->has('auto_login_token')) {
            $token = request()->query('auto_login_token');
            $redirect = redirect()->route('dashboard.settings', ['auto_login_token' => $token])
                ->with('success', 'Paramètres mis à jour avec succès');
        }
        
        return $redirect;
    }
}
