<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('horizon:snapshot')->everyFiveMinutes();
        $schedule->command('cloudflare:reload')->daily();
        $schedule->command('hydrate:top-sallers')->daily();
        $schedule->command('domain:check-cloudflare-status')->hourly();

        $schedule->command('hydrate:fulfilment-customers-status')->daily();

    }


    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
