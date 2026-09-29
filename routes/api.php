<?php

use App\Http\Controllers\Api\LicenseActivationController;
use App\Http\Controllers\Api\LocalNodeBootstrapController;
use App\Http\Controllers\Api\MobileDeviceRegistrationController;
use App\Http\Controllers\Api\MobileSessionRefreshController;
use App\Http\Controllers\Api\MobileSyncPullController;
use App\Http\Controllers\Api\MobileSyncPushController;
use App\Http\Controllers\Api\TrialDeviceRegistrationController;
use Illuminate\Support\Facades\Route;

if ((bool) config('pharmacy.local_node.enabled')) {
    Route::post('/v1/local-node/bootstrap', LocalNodeBootstrapController::class)
        ->middleware(['api.payload', 'throttle:5,1']);
}

Route::middleware('api.payload')->group(function (): void {
    Route::post('/v1/license/activate', LicenseActivationController::class)
        ->middleware('throttle:license-activation');

    Route::post('/v1/mobile/register', MobileDeviceRegistrationController::class)
        ->middleware('throttle:mobile-register');
    Route::post('/v1/mobile/trial/register', TrialDeviceRegistrationController::class)
        ->middleware('throttle:trial-register');
});

Route::prefix('/v1/mobile')
    ->middleware(['api.payload', 'throttle:mobile-api'])
    ->group(function (): void {
        Route::post('/session/refresh', MobileSessionRefreshController::class);
        Route::post('/sync/push', MobileSyncPushController::class);
        Route::get('/sync/pull/{stream}', MobileSyncPullController::class);
    });