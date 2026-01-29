<?php

use App\Http\Controllers\Api\OnboardingApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('onboarding')->group(function () {
    Route::post('/complete', [OnboardingApiController::class, 'complete'])->name('api.onboarding.complete');
    Route::get('/status/{sessionId}', [OnboardingApiController::class, 'status'])->name('api.onboarding.status');
});
