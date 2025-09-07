<?php

namespace App\Console;

use App\Actions\CRM\WebUserPasswordReset\PurgeWebUserPasswordReset;
use App\Actions\Dropshipping\Ebay\Orders\FetchEbayOrders;
use App\Actions\Dropshipping\Ebay\Orders\FetchWooOrders;
use App\Actions\Dropshipping\Shopify\Product\CheckShopifyPortfolios;
use App\Actions\Dropshipping\WooCommerce\Product\UpdateInventoryInWooPortfolio;
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


        $schedule->job(FetchEbayOrders::makeJob())->everyTenMinutes()->sentryMonitor(
            monitorSlug: 'FetchEbayOrders',
        );

        $schedule->job(FetchWooOrders::makeJob())->cron('2,12,22,32,42,52 * * * *')->sentryMonitor(
            monitorSlug: 'FetchWooOrders',
        );

        $schedule->job(UpdateInventoryInWooPortfolio::makeJob())->hourly()->sentryMonitor(
            monitorSlug: 'UpdateWooStockInventories',
        );

        $schedule->job(CheckShopifyPortfolios::makeJob())->dailyAt('03:00')->timezone('UTC')->sentryMonitor(
            monitorSlug: 'CheckShopifyPortfolios',
        );


        (new Schedule())->command('hydrate -s ful')->everyFourHours('23:00')->timezone('UTC');
        (new Schedule())->command('hydrate -s sys')->everyTwoHours('23:00')->timezone('UTC');
        (new Schedule())->command('hydrate:shops')->everyTwoHours('23:00')->timezone('UTC');
        (new Schedule())->command('hydrate:invoice_categories')->everyTwoHours('23:00')->timezone('UTC');

        $schedule->job(ProcessFetchStacks::makeJob())->everyMinute()->timezone('UTC')->sentryMonitor(
            monitorSlug: 'ProcessFetchStacks',
        );

        $schedule->job(SaveWebsitesSitemap::makeJob())->dailyAt('00:00')->timezone('UTC')->sentryMonitor(
            monitorSlug: 'SaveWebsitesSitemap',
        );

        // TODO: We dont need this because we already have fetch orders scheduler above
        // $schedule->command('schedule:platform-orders')->everyMinute()->timezone('UTC')->sentryMonitor('GetPlatformOrders');
    }


    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
