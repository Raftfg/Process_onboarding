<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OnboardingSession;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // Récupérer le sous-domaine
        if (config('app.env') === 'local' && $request->has('subdomain')) {
            $subdomain = $request->get('subdomain');
        } else {
            $host = $request->getHost();
            $subdomain = explode('.', $host)[0];
        }
        
        // Récupérer les informations de l'onboarding
        $onboarding = OnboardingSession::where('subdomain', $subdomain)
            ->where('status', 'completed')
            ->first();
        
        if (!$onboarding) {
            return redirect()->route('welcome', ['subdomain' => $subdomain])
                ->with('error', 'Aucune information d\'onboarding trouvée pour ce sous-domaine.');
        }
        
        return view('dashboard', [
            'onboarding' => $onboarding,
            'subdomain' => $subdomain
        ]);
    }
}
