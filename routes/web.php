<?php

use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route racine : détecte si on est sur un sous-domaine ou le domaine principal
Route::get('/', function (Request $request) {
    $host = $request->getHost();
    $parts = explode('.', $host);
    
    // Détecter si on est sur un sous-domaine
    $isSubdomain = false;
    if (config('app.env') === 'local') {
        if (count($parts) >= 2 && $parts[1] === 'localhost') {
            $isSubdomain = true;
        }
    } else {
        $baseDomain = config('app.subdomain_base_domain', 'akasigroup.local');
        $baseParts = explode('.', $baseDomain);
        if (count($parts) > count($baseParts)) {
            $isSubdomain = true;
        }
    }
    
    // Si c'est un sous-domaine, utiliser SubdomainHomeController
    if ($isSubdomain) {
        return app(\App\Http\Controllers\SubdomainHomeController::class)->index($request);
    }
    
    // Sinon, utiliser OnboardingController pour le domaine principal
    return app(OnboardingController::class)->welcome();
})->name('onboarding.welcome');

// Nouvelles routes d'onboarding (nouveau flux)
Route::get('/onboarding/start', [OnboardingController::class, 'showInitialForm'])->name('onboarding.start');
Route::post('/onboarding/start', [OnboardingController::class, 'storeInitialData'])->name('onboarding.storeInitialData');
Route::get('/onboarding/loading', [OnboardingController::class, 'showLoading'])->name('onboarding.loading');
Route::get('/onboarding/confirmation', [OnboardingController::class, 'showConfirmation'])->name('onboarding.confirmation');
Route::get('/onboarding/activate/{token}', [OnboardingController::class, 'showActivation'])->name('onboarding.activation');
Route::post('/onboarding/activate', [OnboardingController::class, 'activate'])->name('onboarding.activate');

// Anciennes routes (supprimées)

// Routes de connexion racine (domaine principal uniquement)
Route::middleware(['guest', 'root.domain'])->group(function () {
    Route::get('/root-login', [App\Http\Controllers\Auth\RootLoginController::class, 'showLoginForm'])->name('root.login');
    Route::post('/root-login', [App\Http\Controllers\Auth\RootLoginController::class, 'findSubdomains'])->name('root.login.find');
    Route::get('/root-login/subdomains', [App\Http\Controllers\Auth\RootLoginController::class, 'showSubdomains'])->name('root.login.subdomains');
    Route::post('/root-login/select-subdomain', [App\Http\Controllers\Auth\RootLoginController::class, 'selectSubdomain'])->name('root.login.select');
});

// Route de bienvenue (sera utilisée sur les sous-domaines)
// Pour la production, configurez cette route avec Route::domain('{subdomain}.' . config('app.subdomain_base_domain'))
Route::get('/welcome', [WelcomeController::class, 'index'])->name('welcome');

// Route de connexion automatique (accessible sans authentification, utilisée après activation)
Route::get('/auto-login', [OnboardingController::class, 'autoLogin'])->name('auto-login');

// Routes d'authentification (accessibles uniquement aux invités)
Route::middleware(['guest'])->group(function () {
    Route::get('/login', [App\Http\Controllers\Auth\LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [App\Http\Controllers\Auth\LoginController::class, 'login']);
});

// Route de déconnexion (accessible aux utilisateurs authentifiés)
Route::post('/logout', App\Http\Controllers\Auth\LogoutController::class)->name('logout');



// Routes du tableau de bord (protégées par authentification)
Route::middleware(['auth'])->group(function () {
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

// Route pour servir les fichiers storage (fallback si le lien symbolique ne fonctionne pas)
// Cette route est appelée si le fichier n'existe pas dans public/storage
Route::get('/storage/{path}', function ($path) {
    // Nettoyer le chemin pour éviter les attaques de traversal
    $path = str_replace('..', '', $path);
    $path = ltrim($path, '/');
    
    $filePath = storage_path('app/public/' . $path);
    
    if (!file_exists($filePath) || !is_file($filePath)) {
        abort(404, 'File not found');
    }
    
    // Déterminer le type MIME
    $mimeType = mime_content_type($filePath);
    if (!$mimeType) {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
        ];
        $mimeType = $mimeTypes[strtolower($extension)] ?? 'application/octet-stream';
    }
    
    return response()->file($filePath, [
        'Content-Type' => $mimeType,
        'Cache-Control' => 'public, max-age=31536000',
    ]);
})->where('path', '.*')->name('storage.serve');

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