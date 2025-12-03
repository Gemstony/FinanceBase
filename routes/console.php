<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Note: Scheduling should be done in app/Console/Kernel.php, not in routes/console.php
// If you want to schedule telescope pruning, add this to the schedule method in Kernel.php:
// protected function schedule(Schedule $schedule)
// {
//     $schedule->command('telescope:prune --hours=48')->daily();
// }
