<?php

namespace App\Console;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateTopSellers;
use App\Actions\CRM\WebUserPasswordReset\PurgeWebUserPasswordReset;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydrateStatus;
use App\Actions\Fulfilment\UpdateCurrentRecurringBillsTemporalAggregates;
use App\Actions\Helpers\Intervals\ResetDailyIntervals;
use App\Actions\Helpers\Intervals\ResetMonthlyIntervals;
use App\Actions\Helpers\Intervals\ResetQuarterlyIntervals;
use App\Actions\Helpers\Intervals\ResetWeeklyIntervals;
use App\Actions\Helpers\Intervals\ResetYearIntervals;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('horizon:snapshot')->everyFiveMinutes();
        $schedule->command('cloudflare:reload')->daily();
        $schedule->command('domain:check-cloudflare-status')->hourly();


        $schedule->job(ShopHydrateTopSellers::makeJob())->dailyAt('00:00')->timezone('UTC')->sentryMonitor(
            monitorSlug: 'ShopHydrateTopSellers',
        );

        $schedule->job(FulfilmentCustomerHydrateStatus::makeJob())->dailyAt('00:00')->timezone('UTC')->sentryMonitor(
            monitorSlug: 'FulfilmentCustomerHydrateStatus',
        );

        $schedule->job(ResetYearIntervals::makeJob())->yearlyOn(1, 1, '00:00')->timezone('UTC')->sentryMonitor(
            monitorSlug: 'ResetYearIntervals',
        );
        $schedule->job(ResetMonthlyIntervals::makeJob())->monthlyOn(1, '00:00')->timezone('UTC')->sentryMonitor(
            monitorSlug: 'ResetMonthlyIntervals',
        );
        $schedule->job(ResetQuarterlyIntervals::makeJob())->quarterlyOn(1, '00:00')->timezone('UTC')->sentryMonitor(
            monitorSlug: 'ResetQuarterlyIntervals',
        );
        $schedule->job(ResetWeeklyIntervals::makeJob())->weeklyOn(1, '00:00')->timezone('UTC')->sentryMonitor(
            monitorSlug: 'ResetWeeklyIntervals',
        );
        $schedule->job(ResetDailyIntervals::makeJob())->dailyAt('00:00')->timezone('UTC')->sentryMonitor(
            monitorSlug: 'ResetDailyIntervals',
        );

        $schedule->job(UpdateCurrentRecurringBillsTemporalAggregates::makeJob())->dailyAt('00:00')->timezone('UTC')->sentryMonitor(
            monitorSlug: 'UpdateCurrentRecurringBillsTemporalAggregates',
        );

        $schedule->job(PurgeWebUserPasswordReset::makeJob())->dailyAt('03:00')->timezone('UTC')->sentryMonitor(
            monitorSlug: 'PurgeWebUserPasswordReset',
        );

        (new Schedule())->command('hydrate -s ful')->dailyAt('23:00')->timezone('UTC');

    }


    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
