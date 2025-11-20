<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Sept 2025 03:58:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Console;

use App\Actions\CRM\WebUserPasswordReset\PurgeWebUserPasswordReset;
use App\Actions\Fulfilment\ConsolidateRecurringBills;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomersHydrateStatus;
use App\Actions\Fulfilment\UpdateCurrentRecurringBillsTemporalAggregates;
use App\Actions\Helpers\Intervals\ResetDailyIntervals;
use App\Actions\Helpers\Intervals\ResetMonthlyIntervals;
use App\Actions\Helpers\Intervals\ResetQuarterlyIntervals;
use App\Actions\Helpers\Intervals\ResetWeeklyIntervals;
use App\Actions\Helpers\Intervals\ResetYearIntervals;
use App\Actions\Helpers\Isdoc\DeleteTempIsdoc;
use App\Actions\Transfers\FetchStack\ProcessFetchStacks;
use App\Actions\Web\Website\SaveWebsitesSitemap;
use App\Actions\Retina\Dropshipping\Portfolio\PurgeDownloadPortfolioCustomerSalesChannel;
use App\Traits\LoggableSchedule;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    use LoggableSchedule;

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('horizon:snapshot')->everyFiveMinutes();

        $schedule->command('cloudflare:reload')->daily();
        $schedule->command('domain:check-cloudflare-status')->hourly();


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

        $schedule->job(DeleteTempIsdoc::makeJob())->dailyAt('00:00')->timezone('UTC')->sentryMonitor(
            monitorSlug: 'DeleteTempIsdoc',
        );


        $schedule->command('data_feeds:save')->hourly()->timezone('UTC')->sentryMonitor(
            monitorSlug: 'SaveDataFeeds',
        );

        $schedule->command('fetch:orders -w full -B')->everyFiveMinutes()->timezone('UTC')
            ->withoutOverlapping()->sentryMonitor(
                monitorSlug: 'FetchOrdersInBasket',
            );

        $schedule->command('fetch:stock_locations aw')->dailyAt('2:30')
            ->timezone('UTC')->withoutOverlapping()->sentryMonitor(
                monitorSlug: 'FetchAuroraStockLocationsAW',
            );

        $schedule->command('fetch:stock_locations sk')->dailyAt('2:45')
            ->timezone('UTC')->withoutOverlapping()->sentryMonitor(
                monitorSlug: 'FetchAuroraStockLocationsSK',
            );

        $schedule->command('fetch:stock_locations es')->dailyAt('3:00')
            ->timezone('UTC')->withoutOverlapping()->sentryMonitor(
                monitorSlug: 'FetchAuroraStockLocationsES',
            );

        $schedule->command('fetch:stock_locations aroma')->dailyAt('3:15')
            ->timezone('UTC')->withoutOverlapping()->sentryMonitor(
                monitorSlug: 'FetchAuroraStockLocationsAroma',
            );


        $schedule->command('fetch:dispatched_emails -w full -D 2 -N')->everySixHours(15)->withoutOverlapping()
            ->timezone('UTC')->sentryMonitor(
                monitorSlug: 'FetchDispatchedEmails',
            );

        $schedule->command('fetch:email_tracking_events -N -D 2')->twiceDaily(4, 17)->timezone('UTC')->withoutOverlapping()
            ->sentryMonitor(
                monitorSlug: 'FetchEmailTrackingEvents',
            );


        $schedule->command('fetch:ebay-orders')->everyTwoHours()->withoutOverlapping()->sentryMonitor(
            monitorSlug: 'FetchEbayOrders',
        );

        $schedule->command('fetch:woo-orders')->everyTwoHours()->withoutOverlapping()->sentryMonitor(
            monitorSlug: 'FetchWooOrders',
        );

        $schedule->command('woo:ping_active_channel')->everySixHours()->withoutOverlapping()->sentryMonitor(
            monitorSlug: 'PingActiveWooChannel',
        );

        $schedule->command('woo:revive_in_active_channel')->daily()->withoutOverlapping()->sentryMonitor(
            monitorSlug: 'ReviveInActiveWooChannel',
        );

        $schedule->command('ebay:ping')->daily()->withoutOverlapping()->sentryMonitor(
            monitorSlug: 'CheckAllEbayChannels',
        );

        $schedule->command('woo:update-inventory')
            ->hourly()
            ->withoutOverlapping()->sentryMonitor(
                monitorSlug: 'UpdateWooStockInventories',
            );

        $schedule->command('ebay:update-inventory')
            ->everyTwoHours()
            ->withoutOverlapping()->sentryMonitor(
                monitorSlug: 'UpdateInventoryInEbayPortfolio',
            );

        $schedule->command('shopify:update-inventory')->everySixHours()->withoutOverlapping()->sentryMonitor(
            monitorSlug: 'UpdateInventoryInShopifyPortfolio',
        );

        $schedule->command('shopify:check_portfolios grp aw')->dailyAt('03:00')->timezone('UTC')->sentryMonitor(
            monitorSlug: 'CheckShopifyPortfolios',
        );

        $schedule->command('platform-logs:delete')->daily()->sentryMonitor(
            monitorSlug: 'PlatformDeletePortfolioLogs',
        );

        (new Schedule())->command('hydrate -s ful')->everyFourHours('23:00')->timezone('UTC');
        (new Schedule())->command('hydrate -s sys')->everyTwoHours('23:00')->timezone('UTC');
        (new Schedule())->command('hydrate:shops')->everyTwoHours('23:00')->timezone('UTC');
        (new Schedule())->command('hydrate:invoice_categories')->everyTwoHours('23:00')->timezone('UTC');

        $schedule->job(ProcessFetchStacks::makeJob())->everyMinute()->withoutOverlapping()->timezone('UTC')->sentryMonitor(
            monitorSlug: 'ProcessFetchStacks',
        );

        $schedule->job(SaveWebsitesSitemap::makeJob())->dailyAt('00:00')->timezone('UTC')->sentryMonitor(
            monitorSlug: 'SaveWebsitesSitemap',
        );

        $schedule->job(ConsolidateRecurringBills::makeJob())->dailyAt('17:00')->timezone('UTC')->sentryMonitor(
            monitorSlug: 'ConsolidateRecurringBills',
        );

        $schedule->command('hydrate:customers-clv')->dailyAt('01:00')->timezone('UTC')->sentryMonitor(
            monitorSlug: 'HydrateCustomersClv',
        );

        $schedule->command('hydrate:customers-tag')->dailyAt('01:00')->timezone('UTC')->sentryMonitor(
            monitorSlug: 'HydrateCustomersTag',
        );

        $schedule->job(PurgeDownloadPortfolioCustomerSalesChannel::makeJob())->everyMinute()->withoutOverlapping()->timezone('UTC')->sentryMonitor(
            monitorSlug: 'PurgeDownloadPortfolioCustomerSalesChannel',
        );

        $schedule->command('hydrate:ping')->dailyAt('12:59')->withoutOverlapping()->timezone('UTC')->sentryMonitor(
            monitorSlug: 'HydratePing',
        );

        $schedule->command('hydrate:ping')->dailyAt('11:58')->withoutOverlapping()->timezone('UTC')->sentryMonitor(
            monitorSlug: 'HydratePing',
        );

        $schedule->command('hydrate:ping')->dailyAt('13:57')->withoutOverlapping()->timezone('UTC')->sentryMonitor(
            monitorSlug: 'HydratePing',
        );

        $schedule->command('hydrate:scheduled-task-logs --name=test_a')->everyMinute()->withoutOverlapping()->timezone('UTC')->sentryMonitor(
            monitorSlug: 'HydratePing',
        );

        (new Schedule())->command('hydrate:scheduled-task-logs --name=test_b')->everyMinute()->timezone('UTC');


        $schedule->command('hydrate:scheduled-task-logs --name=utc_0')->dailyAt('00:00')->withoutOverlapping()->timezone('UTC')->sentryMonitor(
            monitorSlug: 'HydratePing',
        );
        $schedule->command('hydrate:scheduled-task-logs --name=utc_1')->dailyAt('01:00')->withoutOverlapping()->timezone('UTC')->sentryMonitor(
            monitorSlug: 'HydratePing',
        );
        $schedule->command('hydrate:scheduled-task-logs --name=utc_2')->dailyAt('02:00')->withoutOverlapping()->timezone('UTC')->sentryMonitor(
            monitorSlug: 'HydratePing',
        );
        $schedule->command('hydrate:scheduled-task-logs --name=utc_3')->dailyAt('03:00')->withoutOverlapping()->timezone('UTC')->sentryMonitor(
            monitorSlug: 'HydratePing',
        );
        $schedule->command('hydrate:scheduled-task-logs --name=utc_4')->dailyAt('04:00')->withoutOverlapping()->timezone('UTC')->sentryMonitor(
            monitorSlug: 'HydratePing',
        );
        $schedule->command('hydrate:scheduled-task-logs --name=utc_5')->dailyAt('05:00')->withoutOverlapping()->timezone('UTC')->sentryMonitor(
            monitorSlug: 'HydratePing',
        );
        $schedule->command('hydrate:scheduled-task-logs --name=utc_6')->dailyAt('06:00')->withoutOverlapping()->timezone('UTC')->sentryMonitor(
            monitorSlug: 'HydratePing',
        );
        $schedule->command('hydrate:scheduled-task-logs --name=utc_7')->dailyAt('07:00')->withoutOverlapping()->timezone('UTC')->sentryMonitor(
            monitorSlug: 'HydratePing',
        );
        $schedule->command('hydrate:scheduled-task-logs --name=utc_8')->dailyAt('08:00')->withoutOverlapping()->timezone('UTC')->sentryMonitor(
            monitorSlug: 'HydratePing',
        );
        $schedule->command('hydrate:scheduled-task-logs --name=utc_9')->dailyAt('09:00')->withoutOverlapping()->timezone('UTC')->sentryMonitor(
            monitorSlug: 'HydratePing',
        );
        $schedule->command('hydrate:scheduled-task-logs --name=utc_10')->dailyAt('10:00')->withoutOverlapping()->timezone('UTC')->sentryMonitor(
            monitorSlug: 'HydratePing',
        );
        $schedule->command('hydrate:scheduled-task-logs --name=utc_11')->dailyAt('11:00')->withoutOverlapping()->timezone('UTC')->sentryMonitor(
            monitorSlug: 'HydratePing',
        );
        $schedule->command('hydrate:scheduled-task-logs --name=utc_12')->dailyAt('12:00')->withoutOverlapping()->timezone('UTC')->sentryMonitor(
            monitorSlug: 'HydratePing',
        );
        $schedule->command('hydrate:scheduled-task-logs --name=utc_13')->dailyAt('13:00')->withoutOverlapping()->timezone('UTC')->sentryMonitor(
            monitorSlug: 'HydratePing',
        );
        $schedule->command('hydrate:scheduled-task-logs --name=utc_14')->dailyAt('14:00')->withoutOverlapping()->timezone('UTC')->sentryMonitor(
            monitorSlug: 'HydratePing',
        );
        $schedule->command('hydrate:scheduled-task-logs --name=utc_15')->dailyAt('15:00')->withoutOverlapping()->timezone('UTC')->sentryMonitor(
            monitorSlug: 'HydratePing',
        );
        $schedule->command('hydrate:scheduled-task-logs --name=utc_16')->dailyAt('16:00')->withoutOverlapping()->timezone('UTC')->sentryMonitor(
            monitorSlug: 'HydratePing',
        );
        $schedule->command('hydrate:scheduled-task-logs --name=utc_17')->dailyAt('17:00')->withoutOverlapping()->timezone('UTC')->sentryMonitor(
            monitorSlug: 'HydratePing',
        );
        $schedule->command('hydrate:scheduled-task-logs --name=utc_18')->dailyAt('18:00')->withoutOverlapping()->timezone('UTC')->sentryMonitor(
            monitorSlug: 'HydratePing',
        );
        $schedule->command('hydrate:scheduled-task-logs --name=utc_19')->dailyAt('19:00')->withoutOverlapping()->timezone('UTC')->sentryMonitor(
            monitorSlug: 'HydratePing',
        );
        $schedule->command('hydrate:scheduled-task-logs --name=utc_20')->dailyAt('20:00')->withoutOverlapping()->timezone('UTC')->sentryMonitor(
            monitorSlug: 'HydratePing',
        );
        $schedule->command('hydrate:scheduled-task-logs --name=utc_21')->dailyAt('21:00')->withoutOverlapping()->timezone('UTC')->sentryMonitor(
            monitorSlug: 'HydratePing',
        );
        $schedule->command('hydrate:scheduled-task-logs --name=utc_22')->dailyAt('22:00')->withoutOverlapping()->timezone('UTC')->sentryMonitor(
            monitorSlug: 'HydratePing',
        );
        $schedule->command('hydrate:scheduled-task-logs --name=utc_23')->dailyAt('23:00')->withoutOverlapping()->timezone('UTC')->sentryMonitor(
            monitorSlug: 'HydratePing',
        );
        $schedule->command('hydrate:scheduled-task-logs --name=utc_24')->dailyAt('24:00')->withoutOverlapping()->timezone('UTC')->sentryMonitor(
            monitorSlug: 'HydratePing',
        );

//        $this->logSchedule(
//            $schedule
//                ->command('hydrate:ping')
//                ->everyMinute()
//                ->timezone('UTC'),
//            name: 'test every minutes with $schedule->command()',
//            type: 'command',
//            scheduledAt: 'every minutes'
//        );
//
//        $this->logSchedule(
//            (new Schedule())
//                ->command('hydrate:ping')
//                ->everyMinute()
//                ->timezone('UTC'),
//            name: 'test every minutes with Schedule()',
//            type: 'command',
//            scheduledAt: 'every minutes'
//        );
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
