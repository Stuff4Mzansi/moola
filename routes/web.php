<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Auth\SetupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Middleware\EnsureAdministratorExists;
use Illuminate\Support\Facades\Route;

Route::middleware(EnsureAdministratorExists::class)->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('/setup', [SetupController::class, 'create'])->name('setup.create');
        Route::post('/setup', [SetupController::class, 'store'])->middleware('throttle:6,1')->name('setup.store');
        Route::get('/login', [SessionController::class, 'create'])->name('login');
        Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:20,1')->name('login.store');
    });

    Route::middleware('auth')->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
        Route::resource('subscriptions', SubscriptionController::class);

        Route::prefix('admin')->name('admin.')->middleware('can:users.manage')->group(function (): void {
            Route::resource('users', UserController::class)->only(['index', 'create', 'store', 'update', 'destroy']);
        });
    });
});
