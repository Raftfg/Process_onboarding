<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class OnboardingController extends Controller
{
    public function welcome()
    {
        Session::forget('onboarding_data');
        return view('onboarding.welcome');
    }

    public function step1()
    {
        // Initialiser la session si elle n'existe pas
        if (!Session::has('onboarding_data')) {
            Session::put('onboarding_data', []);
        }
        return view('onboarding.step1');
    }

    public function step2()
    {
        if (!Session::has('onboarding_data.step1')) {
            return redirect()->route('onboarding.step1');
        }
        return view('onboarding.step2');
    }

    public function storeStep1(Request $request)
    {
        $validated = $request->validate([
            'hospital_name' => 'required|string|max:255',
            'hospital_address' => 'nullable|string|max:500',
            'hospital_phone' => 'nullable|string|max:20',
            'hospital_email' => 'nullable|email|max:255',
        ]);

        $onboardingData = Session::get('onboarding_data', []);
        $onboardingData['step1'] = $validated;
        Session::put('onboarding_data', $onboardingData);

        return redirect()->route('onboarding.step2');
    }

    public function storeStep2(Request $request)
    {
        $validated = $request->validate([
            'admin_first_name' => 'required|string|max:255',
            'admin_last_name' => 'required|string|max:255',
            'admin_email' => 'required|email|max:255',
            'admin_password' => 'required|string|min:8|confirmed',
        ]);

        $onboardingData = Session::get('onboarding_data', []);
        $onboardingData['step2'] = $validated;
        Session::put('onboarding_data', $onboardingData);

        return response()->json([
            'success' => true,
            'message' => 'Données enregistrées',
            'session_id' => Session::getId()
        ]);
    }
}
