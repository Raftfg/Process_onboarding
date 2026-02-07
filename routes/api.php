<?php

use App\Http\Controllers\Api\OnboardingApiController;
use App\Http\Controllers\Api\PublicOnboardingController;
use App\Http\Controllers\Api\ApplicationController;
use App\Http\Controllers\Api\ApiKeyManagementController;
use App\Http\Controllers\Api\OnboardingController;
use Illuminate\Support\Facades\Route;

// API publique d'enregistrement (SANS authentification)
Route::prefix('v1/applications')->group(function () {
    Route::post('/register', [ApplicationController::class, 'register'])->name('api.v1.applications.register');
    Route::post('/regenerate-master-key', [ApplicationController::class, 'regenerateMasterKey'])->name('api.v1.applications.regenerate-master-key');
});

// API de gestion des clés API (avec master_key)
Route::prefix('v1/applications/{app_id}')->middleware(['master.key'])->group(function () {
    Route::get('/', [ApplicationController::class, 'show'])->name('api.v1.applications.show');
    Route::post('/retry-database', [ApplicationController::class, 'retryDatabase'])->name('api.v1.applications.retry-database');
    Route::get('/api-keys', [ApiKeyManagementController::class, 'index'])->name('api.v1.applications.api-keys.index');
    Route::post('/api-keys', [ApiKeyManagementController::class, 'store'])->name('api.v1.applications.api-keys.store');
    Route::get('/api-keys/{key_id}', [ApiKeyManagementController::class, 'show'])->name('api.v1.applications.api-keys.show');
    Route::put('/api-keys/{key_id}/config', [ApiKeyManagementController::class, 'updateConfig'])->name('api.v1.applications.api-keys.config');
    Route::delete('/api-keys/{key_id}', [ApiKeyManagementController::class, 'destroy'])->name('api.v1.applications.api-keys.destroy');
});

// API d'onboarding (avec master_key + rate limiting)
Route::prefix('v1/onboarding')->middleware(['master.key'])->group(function () {
    // Nouveau flux stateless orienté orchestration
    Route::post('/start', [OnboardingController::class, 'start'])
        ->middleware('rate.limit.onboarding:start')
        ->name('api.v1.onboarding.start');
    Route::post('/provision', [OnboardingController::class, 'provision'])
        ->middleware('rate.limit.onboarding:provision')
        ->name('api.v1.onboarding.provision');
    Route::get('/status/{uuid}', [OnboardingController::class, 'status'])
        ->middleware('rate.limit.onboarding:status')
        ->name('api.v1.onboarding.status');
    Route::post('/{uuid}/complete', [OnboardingController::class, 'complete'])
        ->middleware('rate.limit.onboarding:status')
        ->name('api.v1.onboarding.complete');

    // Routes de gestion d'enregistrements (admin/observabilité)
    Route::post('/register', [\App\Http\Controllers\Api\OnboardingRegistrationController::class, 'register'])->name('api.v1.onboarding.register');
    Route::get('/', [\App\Http\Controllers\Api\OnboardingRegistrationController::class, 'index'])->name('api.v1.onboarding.index');
    Route::get('/{uuid}', [\App\Http\Controllers\Api\OnboardingRegistrationController::class, 'show'])->name('api.v1.onboarding.show');
    Route::put('/{uuid}/status', [\App\Http\Controllers\Api\OnboardingRegistrationController::class, 'updateStatus'])->name('api.v1.onboarding.update-status');
});

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
        Route::post('/test', [App\Http\Controllers\Api\WebhookController::class, 'triggerTest'])->name('api.webhooks.test');
        Route::delete('/{id}', [App\Http\Controllers\Api\WebhookController::class, 'destroy'])->name('api.webhooks.destroy');
    });

    // API v1 - Onboarding externe (PROTÉGÉ par api.auth)
    Route::prefix('v1')->group(function () {
        Route::post('/onboarding/external', [OnboardingApiController::class, 'externalStore'])->name('api.v1.onboarding.external');
    });
});

// API interne (utilisée par le frontend)
Route::prefix('onboarding')->middleware('web')->group(function () {
    Route::post('/complete', [OnboardingApiController::class, 'complete'])->name('api.onboarding.complete');
    Route::post('/process', [OnboardingApiController::class, 'processAsync'])->name('api.onboarding.process');
    Route::get('/status/{sessionId}', [OnboardingApiController::class, 'status'])->name('api.onboarding.status');
});
