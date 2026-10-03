<?php

use App\Http\Controllers\Api\DesktopAccessManagementController;
use App\Http\Controllers\Api\DesktopLicenseRefreshController;
use App\Http\Controllers\Api\DesktopSessionLoginController;
use App\Http\Controllers\Api\DesktopSessionRefreshController;
use App\Http\Controllers\Api\DesktopSyncPullController;
use App\Http\Controllers\Api\DesktopSyncPushController;
use App\Http\Controllers\Api\DesktopUpdateManifestController;
use App\Http\Controllers\Api\LicenseActivationController;
use App\Http\Controllers\Api\MobileDeviceRegistrationController;
use App\Http\Controllers\Api\MobileSessionRefreshController;
use App\Http\Controllers\Api\MobileSyncPullController;
use App\Http\Controllers\Api\MobileSyncPushController;
use App\Http\Controllers\Api\TrialDeviceRegistrationController;
use Illuminate\Support\Facades\Route;

Route::middleware('api.payload')->group(function (): void {
    Route::post('/v1/license/activate', LicenseActivationController::class)
        ->middleware('throttle:license-activation');

    Route::post('/v1/desktop/license/refresh', DesktopLicenseRefreshController::class)
        ->middleware('throttle:license-activation');

    Route::post('/v1/desktop/session/login', DesktopSessionLoginController::class)
        ->middleware('throttle:license-activation');

    Route::post('/v1/desktop/session/refresh', DesktopSessionRefreshController::class)
        ->middleware('throttle:license-activation');

    Route::post('/v1/mobile/register', MobileDeviceRegistrationController::class)
        ->middleware('throttle:mobile-register');
    Route::post('/v1/mobile/trial/register', TrialDeviceRegistrationController::class)
        ->middleware('throttle:trial-register');
});

Route::prefix('/v1/desktop')
    ->middleware(['api.payload', 'throttle:desktop-api'])
    ->group(function (): void {
        Route::get('/update/manifest', DesktopUpdateManifestController::class);
        Route::post('/sync/push', DesktopSyncPushController::class);
        Route::get('/sync/pull/{stream}', DesktopSyncPullController::class);
        Route::get('/access', [DesktopAccessManagementController::class, 'index']);
        Route::post('/access/users', [DesktopAccessManagementController::class, 'storeUser']);
        Route::put('/access/users/{userId}', [DesktopAccessManagementController::class, 'updateUser']);
        Route::post('/access/roles', [DesktopAccessManagementController::class, 'storeRole']);
        Route::put('/access/roles/{roleId}', [DesktopAccessManagementController::class, 'updateRole']);
    });

Route::prefix('/v1/mobile')
    ->middleware(['api.payload', 'throttle:mobile-api'])
    ->group(function (): void {
        Route::post('/session/refresh', MobileSessionRefreshController::class);
        Route::post('/sync/push', MobileSyncPushController::class);
        Route::get('/sync/pull/{stream}', MobileSyncPullController::class);
    });
