<?php

namespace App\Providers;

use App\Models\TaskList;
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
        Gate::define('manage-branding', fn (User $user) => $user->isOwner());

        // Profile pictures are visible to people who work together somewhere.
        Gate::define('view-avatar', fn (User $viewer, User $user) => $viewer->is($user)
            || $viewer->isOwner()
            || $user->isOwner()
            || TaskList::visibleTo($viewer)->whereIn('id', TaskList::visibleTo($user)->select('id'))->exists());
    }
}
