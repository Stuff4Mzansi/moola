<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\SetupController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health/live', [HealthController::class, 'live'])
    ->name('health.live');
Route::get('/health/ready', [HealthController::class, 'ready'])
    ->name('health.ready');

Route::get('/setup', [SetupController::class, 'create'])->name('setup.create');
Route::post('/setup', [SetupController::class, 'store'])->name('setup.store');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function (): void {
    Route::get('/password/change', [PasswordChangeController::class, 'edit'])
        ->name('password.change');
    Route::put('/password/change', [PasswordChangeController::class, 'update'])
        ->name('password.update');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
