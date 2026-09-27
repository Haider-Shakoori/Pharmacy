<?php

use App\Http\Controllers\Api\LicenseActivationController;
use Illuminate\Support\Facades\Route;

Route::post('/v1/license/activate', LicenseActivationController::class)
    ->middleware('throttle:30,1');
