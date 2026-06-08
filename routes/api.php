<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Route;

Route::get('health', [HealthController::class, 'check'])->name('health');
Route::get('health/auth', [HealthController::class, 'authBackend'])->name('health.auth');

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('login', [LoginController::class, 'login'])->name('login');
    Route::get('validate-access-token', [LoginController::class, 'validateAccessToken'])->name('validate-access-token');
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');
});
