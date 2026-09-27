<?php

use App\Http\Controllers\Pharmacy\DashboardController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/pharmacy');

Route::get('/pharmacy', DashboardController::class)
    ->name('pharmacy.dashboard');

Route::get('/locale/{locale}', function (string $locale) {
    abort_unless(in_array($locale, config('pharmacy.locales'), true), 404);

    session(['locale' => $locale]);

    return back();
})->name('locale.switch');
