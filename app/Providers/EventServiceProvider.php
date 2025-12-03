<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        \Illuminate\Auth\Events\Login::class => [
            \Rappasoft\LaravelAuthenticationLog\Listeners\LoginListener::class,
        ],
        \Illuminate\Auth\Events\Failed::class => [
            \Rappasoft\LaravelAuthenticationLog\Listeners\FailedLoginListener::class,
        ],
        \Illuminate\Auth\Events\Logout::class => [
            \Rappasoft\LaravelAuthenticationLog\Listeners\LogoutListener::class,
        ],
        \Illuminate\Auth\Events\OtherDeviceLogout::class => [
            \Rappasoft\LaravelAuthenticationLog\Listeners\OtherDeviceLogoutListener::class,
        ],
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
