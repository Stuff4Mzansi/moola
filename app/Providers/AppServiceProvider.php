<?php

namespace App\Providers;

use App\CurrencySettings;
use App\Http\Controllers\NotificationController;
use App\Models\BudgetTransaction;
use App\Models\SavingsContribution;
use App\Models\User;
use App\Observers\DebtBudgetPaymentObserver;
use App\Observers\SavingsBudgetObserver;
use App\Observers\SavingsContributionObserver;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\View::composer('*', function (View $view): void {
            $currency = app(CurrencySettings::class);
            $view->with(['currencyCode' => $currency->code(), 'currencySymbol' => $currency->symbol(), 'currencyPrefix' => $currency->prefix()]);
        });
        \Illuminate\Support\Facades\View::composer('layouts.app', function (View $view): void {
            if (auth()->check()) {
                $view->with(app(NotificationController::class)->bellData(request()));
            }
        });
        Event::listen(DiagnosingHealth::class, fn (): mixed => DB::select('select 1'));
        BudgetTransaction::observe(DebtBudgetPaymentObserver::class);
        BudgetTransaction::observe(SavingsBudgetObserver::class);
        SavingsContribution::observe(SavingsContributionObserver::class);
        Gate::define('settings.manage', fn (User $user): bool => $user->isAdmin());
        Gate::define('users.manage', fn (User $user): bool => $user->isAdmin());
        Gate::define('users.assign-role', fn (User $actor, User $target): bool => $actor->isAdmin() && ! $target->isSuperAdmin() && ! $actor->is($target)
        );
        Gate::define('users.delete', fn (User $actor, User $target): bool => $actor->isAdmin() && ! $target->isSuperAdmin() && ! $actor->is($target)
        );
    }
}
