<?php

use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;

// Page de démarrage - recherche de domaine
Route::get('/', [App\Http\Controllers\StartController::class, 'index'])->name('start');
Route::post('/find-domains', [App\Http\Controllers\StartController::class, 'findDomains'])->name('find.domains');
Route::get('/select-domain', [App\Http\Controllers\StartController::class, 'selectDomain'])->name('select.domain');

// Routes d'onboarding (sur le domaine principal)
// Note: Pour la production, utilisez Route::domain() pour séparer les routes
Route::get('/onboarding', [OnboardingController::class, 'welcome'])->name('onboarding.welcome');
Route::get('/step1', [OnboardingController::class, 'step1'])->name('onboarding.step1');
Route::get('/step2', [OnboardingController::class, 'step2'])->name('onboarding.step2');
Route::post('/step1', [OnboardingController::class, 'storeStep1'])->name('onboarding.storeStep1');
Route::post('/step2', [OnboardingController::class, 'storeStep2'])->name('onboarding.storeStep2');

// Route de bienvenue (sera utilisée sur les sous-domaines)
// Pour la production, configurez cette route avec Route::domain('{subdomain}.' . config('app.subdomain_base_domain'))
Route::get('/welcome', [WelcomeController::class, 'index'])->name('welcome');

// Routes d'authentification (accessibles uniquement aux invités)
Route::middleware(['guest'])->group(function () {
    Route::get('/login', [App\Http\Controllers\Auth\LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [App\Http\Controllers\Auth\LoginController::class, 'login']);
});

// Route de déconnexion (accessible aux utilisateurs authentifiés)
// Support à la fois GET et POST pour la déconnexion
Route::match(['get', 'post'], '/logout', App\Http\Controllers\Auth\LogoutController::class)->name('logout');

// Route du tableau de bord (protégée par authentification)
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
    
    // Routes de configuration du dashboard
    Route::get('/dashboard/config', [App\Http\Controllers\DashboardConfigController::class, 'index'])->name('dashboard.config');
    Route::post('/dashboard/config', [App\Http\Controllers\DashboardConfigController::class, 'store'])->name('dashboard.config.store');
    Route::post('/dashboard/config/theme', [App\Http\Controllers\DashboardConfigController::class, 'updateTheme'])->name('dashboard.config.theme');
});

// Routes d'administration (protégées par middleware admin)
Route::prefix('admin')->name('admin.')->group(function () {
    // Authentification admin
    Route::middleware(['guest'])->group(function () {
        Route::get('/login', [App\Http\Controllers\Admin\AuthController::class, 'showLoginForm'])->name('login');
        Route::post('/login', [App\Http\Controllers\Admin\AuthController::class, 'login']);
    });

    // Routes protégées par authentification admin
    Route::middleware(['admin'])->group(function () {
        Route::post('/logout', [App\Http\Controllers\Admin\AuthController::class, 'logout'])->name('logout');
        Route::get('/dashboard', [App\Http\Controllers\Admin\TenantController::class, 'dashboard'])->name('dashboard');
        
        // Gestion des tenants
        Route::get('/tenants', [App\Http\Controllers\Admin\TenantController::class, 'index'])->name('tenants.index');
        Route::get('/tenants/{id}', [App\Http\Controllers\Admin\TenantController::class, 'show'])->name('tenants.show');
        Route::post('/tenants/{id}/status', [App\Http\Controllers\Admin\TenantController::class, 'updateStatus'])->name('tenants.updateStatus');
        Route::delete('/tenants/{id}', [App\Http\Controllers\Admin\TenantController::class, 'destroy'])->name('tenants.destroy');
        Route::post('/tenants/{id}/restore', [App\Http\Controllers\Admin\TenantController::class, 'restore'])->name('tenants.restore');
    });
});