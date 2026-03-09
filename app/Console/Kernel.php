<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\WriteOffExpiredBatches::class,
        \App\Console\Commands\CheckExpiryAlerts::class,
        \App\Console\Commands\CheckSubscriptionExpiryAlerts::class,
        \App\Console\Commands\SuspendExpiredShops::class,
        \App\Console\Commands\ProcessLoanPenalties::class,
        \App\Console\Commands\ProcessLoanInterestAccrual::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Run expired-batch write-offs daily at 02:00 AM server time
        // $schedule->command('writeoff:expired-batches')->dailyAt('02:00');

        // Run expiry alerts checks daily at 09:00 AM server time
        // $schedule->command('inventory:check-expiry-alerts')->dailyAt('09:00');
        // $schedule->command('subscriptions:check-expiry-alerts')->dailyAt('09:00');

        // Run shop suspension check daily at 09:30 AM server time
        // $schedule->command('shops:suspend-expired')->dailyAt('09:30');

        // Process overdue installment penalties daily at 00:10 AM server time
        $schedule->command('loans:process-penalties')->dailyAt('00:10');

        // Accrue daily interest for active loans at 00:05 AM server time
        $schedule->command('loans:accrue-interest')->dailyAt('00:05');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }


}
