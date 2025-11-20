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
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
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

        $schedule->command('hydrate:customers-clv')->dailyAt('01:00')->withoutOverlapping()->timezone('UTC')->sentryMonitor(
            monitorSlug: 'HydrateCustomersClv',
        );

        $schedule->command('hydrate:customers-tag')->dailyAt('01:00')->withoutOverlapping()->timezone('UTC')->sentryMonitor(
            monitorSlug: 'HydrateCustomersTag',
        );

        $schedule->command('hydrate:ping')->dailyAt('02:20')->withoutOverlapping()->timezone('UTC')->sentryMonitor(
            monitorSlug: 'HydratePing',
        );
    }


    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
