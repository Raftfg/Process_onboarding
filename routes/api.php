<?php

use App\Http\Controllers\Api\OnboardingApiController;
use App\Http\Controllers\Api\PublicOnboardingController;
use Illuminate\Support\Facades\Route;

// API publique pour intégration externe (protégée par authentification API)
Route::middleware(['api.auth'])->group(function () {
    Route::prefix('onboarding')->group(function () {
        // Créer un onboarding (API publique)
        Route::post('/create', [PublicOnboardingController::class, 'create'])->name('api.onboarding.create');
        
        // Obtenir le statut d'un onboarding
        Route::get('/status/{subdomain}', [PublicOnboardingController::class, 'status'])->name('api.onboarding.status');
    });

    // API pour obtenir les informations d'un tenant
    Route::prefix('tenant')->group(function () {
        Route::get('/{subdomain}', [PublicOnboardingController::class, 'getTenant'])->name('api.tenant.get');
    });

    // API pour gérer les webhooks
    Route::prefix('webhooks')->group(function () {
        Route::post('/register', [App\Http\Controllers\Api\WebhookController::class, 'register'])->name('api.webhooks.register');
        Route::get('/', [App\Http\Controllers\Api\WebhookController::class, 'index'])->name('api.webhooks.index');
        Route::delete('/{id}', [App\Http\Controllers\Api\WebhookController::class, 'destroy'])->name('api.webhooks.destroy');
    });
});

// API interne (utilisée par le frontend)
Route::prefix('onboarding')->middleware('web')->group(function () {
    Route::post('/complete', [OnboardingApiController::class, 'complete'])->name('api.onboarding.complete');
    Route::get('/status/{sessionId}', [OnboardingApiController::class, 'status'])->name('api.onboarding.status');
});
