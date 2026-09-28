<?php

use App\Http\Controllers\Api\LicenseActivationController;
use App\Http\Controllers\Api\MobileDeviceRegistrationController;
use Illuminate\Support\Facades\Route;

Route::post('/v1/license/activate', LicenseActivationController::class)
    ->middleware('throttle:30,1');

Route::post('/v1/mobile/register', MobileDeviceRegistrationController::class)
    ->middleware('throttle:10,1');
