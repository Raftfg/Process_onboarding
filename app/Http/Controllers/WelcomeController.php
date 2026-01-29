<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WelcomeController extends Controller
{
    public function index(Request $request)
    {
        // Vérifier si c'est une redirection après onboarding
        $isWelcome = $request->has('welcome');
        
        // Récupérer le sous-domaine actuel
        $host = $request->getHost();
        $subdomain = explode('.', $host)[0];
        
        return view('welcome', [
            'isWelcome' => $isWelcome,
            'subdomain' => $subdomain
        ]);
    }
}
