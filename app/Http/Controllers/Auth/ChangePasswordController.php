<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ChangePasswordController extends Controller
{
    /**
     * Afficher le formulaire de changement de mot de passe
     */
    public function showChangePasswordForm()
    {
        return view('auth.change-password');
    }

    /**
     * Traiter le changement de mot de passe
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = Auth::user();

        // Vérifier que le mot de passe actuel est correct
        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors([
                'current_password' => 'Le mot de passe actuel est incorrect.',
            ])->withInput();
        }

        // Vérifier que le nouveau mot de passe est différent de l'ancien
        if (Hash::check($request->password, $user->password)) {
            return back()->withErrors([
                'password' => 'Le nouveau mot de passe doit être différent de l\'ancien.',
            ])->withInput();
        }

        // Mettre à jour le mot de passe
        $user->password = Hash::make($request->password);
        $user->password_changed_at = now();
        $user->save();

        Log::info('Mot de passe changé pour l\'utilisateur: ' . $user->email);

        return redirect()->route('dashboard')
            ->with('success', 'Votre mot de passe a été modifié avec succès.');
    }
}
