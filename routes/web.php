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

// Routes d'authentification (accessibles uniquement aux invités)
Route::middleware(['guest'])->group(function () {
    Route::get('/login', [App\Http\Controllers\Auth\LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [App\Http\Controllers\Auth\LoginController::class, 'login']);
});

// Route de déconnexion (accessible aux utilisateurs authentifiés)
Route::post('/logout', App\Http\Controllers\Auth\LogoutController::class)->name('logout');

// Route de changement de mot de passe (accessible aux utilisateurs authentifiés)
Route::middleware(['auth'])->group(function () {
    Route::get('/change-password', [App\Http\Controllers\Auth\ChangePasswordController::class, 'showChangePasswordForm'])->name('password.change');
    Route::post('/change-password', [App\Http\Controllers\Auth\ChangePasswordController::class, 'changePassword']);
});

// Routes du tableau de bord (protégées par authentification et changement de mot de passe)
Route::middleware(['auth', 'force.password.change'])->group(function () {
    // Dashboard principal
    Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
    
    // Statistiques
    Route::get('/dashboard/stats', [App\Http\Controllers\Dashboard\StatsController::class, 'index'])->name('dashboard.stats');
    Route::get('/dashboard/stats/chart', [App\Http\Controllers\Dashboard\StatsController::class, 'chart'])->name('dashboard.stats.chart');
    
    // Activités
    Route::get('/dashboard/activities', [App\Http\Controllers\Dashboard\ActivityController::class, 'index'])->name('dashboard.activities');
    Route::post('/dashboard/activities', [App\Http\Controllers\Dashboard\ActivityController::class, 'store'])->name('dashboard.activities.store');
    
    // Notifications
    Route::get('/dashboard/notifications', [App\Http\Controllers\Dashboard\NotificationController::class, 'index'])->name('dashboard.notifications');
    Route::post('/dashboard/notifications/{id}/read', [App\Http\Controllers\Dashboard\NotificationController::class, 'markAsRead'])->name('dashboard.notifications.read');
    Route::post('/dashboard/notifications/read-all', [App\Http\Controllers\Dashboard\NotificationController::class, 'markAllAsRead'])->name('dashboard.notifications.read-all');
    Route::get('/dashboard/notifications/unread-count', [App\Http\Controllers\Dashboard\NotificationController::class, 'unreadCount'])->name('dashboard.notifications.unread-count');
    
    // Recherche
    Route::get('/dashboard/search', [App\Http\Controllers\Dashboard\SearchController::class, 'search'])->name('dashboard.search');
    
    // Paramètres
    Route::get('/dashboard/settings', [App\Http\Controllers\Dashboard\SettingsController::class, 'index'])->name('dashboard.settings');
    Route::put('/dashboard/settings', [App\Http\Controllers\Dashboard\SettingsController::class, 'update'])->name('dashboard.settings.update');
    
    // Rapports
    Route::get('/dashboard/reports', function () {
        return view('dashboard.reports.index');
    })->name('dashboard.reports');
    
    // Utilisateurs
    Route::resource('dashboard/users', App\Http\Controllers\UsersController::class)->names([
        'index' => 'dashboard.users.index',
        'create' => 'dashboard.users.create',
        'store' => 'dashboard.users.store',
        'show' => 'dashboard.users.show',
        'edit' => 'dashboard.users.edit',
        'update' => 'dashboard.users.update',
        'destroy' => 'dashboard.users.destroy',
    ]);
    
    // Personnalisation
    Route::get('/dashboard/customization', [App\Http\Controllers\Dashboard\CustomizationController::class, 'index'])->name('dashboard.customization');
    Route::post('/dashboard/customization/branding', [App\Http\Controllers\Dashboard\CustomizationController::class, 'updateBranding'])->name('dashboard.customization.branding');
    Route::post('/dashboard/customization/layout', [App\Http\Controllers\Dashboard\CustomizationController::class, 'updateLayout'])->name('dashboard.customization.layout');
    Route::post('/dashboard/customization/menu', [App\Http\Controllers\Dashboard\CustomizationController::class, 'updateMenu'])->name('dashboard.customization.menu');
    Route::post('/dashboard/customization/logo', [App\Http\Controllers\Dashboard\CustomizationController::class, 'uploadLogo'])->name('dashboard.customization.logo');
    Route::post('/dashboard/customization/reset', [App\Http\Controllers\Dashboard\CustomizationController::class, 'reset'])->name('dashboard.customization.reset');
    Route::post('/dashboard/customization/preview', [App\Http\Controllers\Dashboard\CustomizationController::class, 'preview'])->name('dashboard.customization.preview');
});

// Routes admin (domaine principal uniquement)
Route::prefix('admin')->group(function () {
    // Authentification admin
    Route::middleware(['guest.admin'])->group(function () {
        Route::get('/login', [App\Http\Controllers\Admin\Auth\AdminLoginController::class, 'showLoginForm'])->name('admin.login');
        Route::post('/login', [App\Http\Controllers\Admin\Auth\AdminLoginController::class, 'login']);
    });
    
    Route::post('/logout', [App\Http\Controllers\Admin\Auth\AdminLoginController::class, 'logout'])->name('admin.logout');
    
    // Routes protégées
    Route::middleware(['admin'])->group(function () {
        Route::get('/dashboard', [App\Http\Controllers\Admin\AdminDashboardController::class, 'index'])->name('admin.dashboard');
        Route::resource('/tenants', App\Http\Controllers\Admin\TenantController::class)->names([
            'index' => 'admin.tenants.index',
            'show' => 'admin.tenants.show',
            'destroy' => 'admin.tenants.destroy',
        ]);
        Route::get('/tenants/{id}/stats', [App\Http\Controllers\Admin\TenantController::class, 'getStats'])->name('admin.tenants.stats');
        Route::post('/tenants/{id}/toggle-status', [App\Http\Controllers\Admin\TenantController::class, 'toggleStatus'])->name('admin.tenants.toggle-status');
    });
});