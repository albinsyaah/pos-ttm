<?php

namespace App\Providers;

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
        // Super Admin always passes every permission/ability check, so a
        // Super Admin never has to be granted individual permissions.
        Gate::before(function ($user, string $ability) {
            return $user->isSuperAdmin() ? true : null;
        });
    }
}
