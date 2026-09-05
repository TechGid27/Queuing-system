<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Auto-skip students who don't respond within 3 minutes
        $schedule->command('queue:auto-skip')->everyMinute();

        // Auto-pause at lunch break start, auto-resume at lunch break end
        // Times are configurable via the settings table (lunch_break_start / lunch_break_end)
        $schedule->command('queue:lunch-break')->everyMinute();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
