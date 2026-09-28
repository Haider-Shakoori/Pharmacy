<?php

use App\Http\Controllers\Api\LicenseActivationController;
use App\Http\Controllers\Api\MobileDeviceRegistrationController;
use App\Http\Controllers\Api\MobileSessionRefreshController;
use App\Http\Controllers\Api\MobileSyncPullController;
use App\Http\Controllers\Api\MobileSyncPushController;
use Illuminate\Support\Facades\Route;

Route::post('/v1/license/activate', LicenseActivationController::class)
    ->middleware('throttle:30,1');

Route::post('/v1/mobile/register', MobileDeviceRegistrationController::class)
    ->middleware('throttle:10,1');

Route::prefix('/v1/mobile')->middleware('throttle:120,1')->group(function (): void {
    Route::post('/session/refresh', MobileSessionRefreshController::class);
    Route::post('/sync/push', MobileSyncPushController::class);
    Route::get('/sync/pull/{stream}', MobileSyncPullController::class);
});
