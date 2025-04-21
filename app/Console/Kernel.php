<?php

namespace App\Console;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateTopSellers;
use App\Actions\CRM\WebUserPasswordReset\PurgeWebUserPasswordReset;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomersHydrateStatus;
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

        $schedule->job(FulfilmentCustomersHydrateStatus::makeJob())->dailyAt('00:00')->timezone('UTC')->sentryMonitor(
            monitorSlug: 'FulfilmentCustomersHydrateStatus',
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

        $schedule->command('fetch:orders -w full -B')->everyFiveMinutes()->timezone('UTC')->sentryMonitor(
            monitorSlug: 'FetchOrdersInBasket',
        );

        $schedule->command('fetch:dispatched_emails -w full -D 2 -N')->everySixHours(15)
            ->timezone('UTC')->sentryMonitor(
                monitorSlug: 'FetchOrdersInBasket',
            );

        $schedule->command('fetch:email_tracking_events -N -D 2')->twiceDaily(11, 23)->timezone('UTC')
            ->sentryMonitor(
                monitorSlug: 'FetchOrdersInBasket',
            );


        (new Schedule())->command('hydrate -s ful')->everyFourHours('23:00')->timezone('UTC');
        (new Schedule())->command('hydrate -s sys')->everyTwoHours('23:00')->timezone('UTC');
        (new Schedule())->command('hydrate:shops')->everyTwoHours('23:00')->timezone('UTC');
        (new Schedule())->command('hydrate:invoice_categories')->everyTwoHours('23:00')->timezone('UTC');

    }


    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
