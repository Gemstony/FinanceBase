<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Telescope::night();

        $this->hideSensitiveRequestDetails();

        $isLocal = $this->app->environment('local');

        Telescope::filter(function (IncomingEntry $entry) use ($isLocal) {
            return $isLocal ||
                   $entry->isReportableException() ||
                   $entry->isFailedRequest() ||
                   $entry->isFailedJob() ||
                   $entry->isScheduledTask() ||
                   $entry->hasMonitoredTag();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register Telescope routes with our custom middleware
        $this->registerTelescopeRoutes();

        parent::boot();
    }

    /**
     * Register the Telescope routes with custom middleware.
     */
    protected function registerTelescopeRoutes(): void
    {
        if (!$this->app->environment('local') && !$this->isTelescopeEnabled()) {
            return;
        }

        $this->app['router']->middleware(['web', 'telescope.super-admin'])
            ->prefix(config('telescope.path', 'telescope'))
            ->namespace('Laravel\Telescope\Http\Controllers')
            ->group(function () {
                // This will load the default Telescope routes but with our middleware
                $this->loadRoutesFrom(__DIR__.'/../../vendor/laravel/telescope/routes/web.php');
            });
    }

    /**
     * Check if Telescope is enabled.
     */
    protected function isTelescopeEnabled(): bool
    {
        return config('telescope.enabled', true);
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        Telescope::hideRequestParameters(['_token']);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }

}
