<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OnboardingSession;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // Récupérer le sous-domaine depuis l'URL
        $host = $request->getHost();
        $parts = explode('.', $host);
        
        // En local, le format est: subdomain.localhost
        // En production, le format est: subdomain.domain.com
        if (count($parts) >= 2) {
            $subdomain = $parts[0];
            // Si c'est localhost, on a le sous-domaine
            if ($parts[1] === 'localhost' || (count($parts) >= 3 && $parts[1] !== 'www')) {
                // Le sous-domaine est le premier élément
            } else {
                // En production, extraire le sous-domaine
                $subdomain = $parts[0];
            }
        } else {
            // Fallback: essayer depuis le paramètre (pour compatibilité)
            $subdomain = $request->get('subdomain');
        }
        
        if (!$subdomain) {
            return redirect('/')->with('error', 'Sous-domaine non trouvé.');
        }
        
        // Récupérer les informations de l'onboarding
        $onboarding = OnboardingSession::where('subdomain', $subdomain)
            ->where('status', 'completed')
            ->first();
        
        if (!$onboarding) {
            return redirect(subdomain_url($subdomain, '/welcome'))
                ->with('error', 'Aucune information d\'onboarding trouvée pour ce sous-domaine.');
        }
        
        return view('dashboard', [
            'onboarding' => $onboarding,
            'subdomain' => $subdomain
        ]);
    }
}
