
<?php

// File: bootstrap/app.php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use App\Http\Middleware\CheckUserHasShop;
use App\Http\Middleware\EnsureSubshopAccess;
use App\Http\Middleware\TelescopeSuperAdminMiddleware;
use App\Http\Middleware\InjectUiTheme;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )


    ->withSchedule(function (Schedule $schedule) {
        // Telescope pruning - clean up old entries after 48 hours
        $schedule->command('telescope:prune --hours=2')->daily();

        // Check for expired inventory batches daily at 9 AM
        $schedule->command('inventory:check-expiry-alerts')->dailyAt('09:00');
    })
    ->withMiddleware(function (Middleware $middleware) {
        // HAPA ndio unaongeza middleware yako
        $middleware->alias([
            'has.shop' => CheckUserHasShop::class,
            'subshop.access' => EnsureSubshopAccess::class,
            'role' => RoleMiddleware::class,
            'telescope.super-admin' => TelescopeSuperAdminMiddleware::class,
        ]);

        // Inject UI theme variables globally for all HTML responses
        $middleware->append(InjectUiTheme::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

    