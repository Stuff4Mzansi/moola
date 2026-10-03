<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
        Gate::define('users.manage', fn (User $user): bool => $user->isAdmin());
        Gate::define('users.assign-role', fn (User $actor, User $target): bool => $actor->isAdmin() && ! $target->isSuperAdmin() && ! $actor->is($target)
        );
        Gate::define('users.delete', fn (User $actor, User $target): bool => $actor->isAdmin() && ! $target->isSuperAdmin() && ! $actor->is($target)
        );
    }
}
