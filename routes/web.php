<?php

use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;

// Routes d'onboarding (sur le domaine principal)
// Note: Pour la production, utilisez Route::domain() pour séparer les routes
Route::get('/', [OnboardingController::class, 'welcome'])->name('onboarding.welcome');
Route::get('/step1', [OnboardingController::class, 'step1'])->name('onboarding.step1');
Route::get('/step2', [OnboardingController::class, 'step2'])->name('onboarding.step2');
Route::post('/step1', [OnboardingController::class, 'storeStep1'])->name('onboarding.storeStep1');
Route::post('/step2', [OnboardingController::class, 'storeStep2'])->name('onboarding.storeStep2');

// Route de bienvenue (sera utilisée sur les sous-domaines)
// Pour la production, configurez cette route avec Route::domain('{subdomain}.' . config('app.subdomain_base_domain'))
Route::get('/welcome', [WelcomeController::class, 'index'])->name('welcome');
