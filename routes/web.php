<?php

use App\Http\Controllers\Admin\BudgetController as AdminBudgetController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Auth\SetupController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DebtController;
use App\Http\Controllers\SavingsGoalController;
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
        Route::resource('debts', DebtController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('/debts/{debt}/interest', [DebtController::class, 'interest'])->name('debts.interest');
        Route::post('/debts/{debt}/payments', [DebtController::class, 'payment'])->name('debts.payments.store');
        Route::delete('/debts/{debt}/payments/{payment}', [DebtController::class, 'removePayment'])->name('debts.payments.destroy');
        Route::post('/debts/{debt}/payments/{payment}/restore', [DebtController::class, 'restorePayment'])->withTrashed()->name('debts.payments.restore');
        Route::resource('goals', SavingsGoalController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('/goals/{goal}/contributions', [SavingsGoalController::class, 'contribute'])->name('goals.contributions.store');
        Route::delete('/goals/{goal}/contributions/{contribution}', [SavingsGoalController::class, 'remove'])->name('goals.contributions.destroy');
        Route::post('/goals/{goal}/contributions/{contribution}/restore', [SavingsGoalController::class, 'restore'])->withTrashed()->name('goals.contributions.restore');
        Route::get('/budgets', [BudgetController::class, 'index'])->name('budgets.index');
        Route::post('/budgets', [BudgetController::class, 'store'])->name('budgets.store');
        Route::post('/budgets/{budget}/periods', [BudgetController::class, 'storePeriod'])->name('budgets.periods.store');
        Route::post('/budget-periods/{period}/{action}', [BudgetController::class, 'action'])->name('budgets.action')->whereIn('action', ['recurring-load', 'recurring-save', 'recurring-remove', 'recurring-pay', 'budget-rename', 'group-save', 'group-remove', 'category-group', 'period-preview', 'period-save', 'category-save', 'category-remove', 'income-save', 'income-remove', 'expense-save', 'expense-remove', 'expense-restore', 'commitment-pay', 'transfer', 'member-save', 'settings-save']);

        Route::prefix('admin')->name('admin.')->middleware('can:users.manage')->group(function (): void {
            Route::resource('budgets', AdminBudgetController::class)->only(['index', 'destroy']);
            Route::resource('users', UserController::class)->only(['index', 'create', 'store', 'update', 'destroy']);
        });
    });
});
