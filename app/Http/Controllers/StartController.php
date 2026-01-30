<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\TenantService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class StartController extends Controller
{
    protected $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    /**
     * Affiche la page de démarrage
     */
    public function index()
    {
        // S'assurer qu'on utilise la base principale
        Config::set('database.default', 'mysql');
        DB::purge('tenant');

        return view('start');
    }

    /**
     * Recherche les domaines disponibles pour un email
     */
    public function findDomains(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // S'assurer qu'on utilise la base principale
        Config::set('database.default', 'mysql');
        DB::purge('tenant');

        $email = $request->input('email');
        
        // Rechercher les tenants où cet email existe
        $tenants = $this->tenantService->findTenantsByUserEmail($email);

        if (empty($tenants)) {
            return back()->withErrors([
                'email' => 'Aucun compte trouvé pour cet email. Veuillez vérifier votre adresse email ou créer un nouveau compte.',
            ])->withInput();
        }

        // Stocker l'email dans la session pour la prochaine étape
        session(['login_email' => $email]);

        // Rediriger vers la page de sélection de domaine
        return redirect()->route('select.domain')->with('tenants', $tenants);
    }

    /**
     * Affiche la page de sélection de domaine
     */
    public function selectDomain(Request $request)
    {
        // S'assurer qu'on utilise la base principale
        Config::set('database.default', 'mysql');
        DB::purge('tenant');

        $tenants = session('tenants', []);
        $email = session('login_email');

        if (empty($tenants) || !$email) {
            return redirect()->route('start')
                ->with('error', 'Veuillez d\'abord rechercher votre email.');
        }

        return view('select-domain', [
            'tenants' => $tenants,
            'email' => $email,
        ]);
    }
}

