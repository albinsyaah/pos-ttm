<?php

namespace App\Providers;

use App\Services\PayableService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
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

        // Due-date reminders for the navbar bell and the pop-up shown after
        // login. Only users holding notifications.view get any; for everyone
        // else nothing is queried. The reminders are computed live, so paying
        // an invoice in full makes its reminder disappear on its own.
        View::composer('layouts.app', function ($view) {
            $user = auth()->user();
            $reminders = collect();
            $showPopup = false;

            if ($user !== null && $user->can('notifications.view')) {
                $reminders = app(PayableService::class)->reminders();
                $showPopup = session()->pull('show_due_popup', false) && $reminders->isNotEmpty();
            } else {
                session()->forget('show_due_popup');
            }

            $view->with('dueReminders', $reminders)->with('showDuePopup', $showPopup);
        });
    }
}
