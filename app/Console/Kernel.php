<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Sept 2025 03:58:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Console;

use App\Actions\Catalogue\Shop\External\Faire\GetFaireOrdersAllShops;
use App\Actions\Catalogue\Shop\External\Faire\GetFaireProductsAllShops;
use App\Actions\Comms\Mailshot\RunMailshotScheduled;
use App\Actions\Comms\Mailshot\RunMailshotSecondWave;
use App\Actions\Comms\Mailshot\RunMailshotTrackingUpdates;
use App\Actions\Comms\Mailshot\RunNewsletterScheduled;
use App\Actions\Comms\Outbox\BackInStockNotification\RunBackInStockEmailBulkRuns;
use App\Actions\Comms\Outbox\LowStockInBasket\RunBasketLowStockEmailBulkRuns;
use App\Actions\Comms\Outbox\PriceChangeNotification\RunPriceChangeNotificationEmailBulkRuns;
use App\Actions\Comms\Outbox\ReorderRemainder\RunReorderRemainderEmailBulkRuns;
use App\Actions\CRM\Customer\PruneCustomerWebActivities;
use App\Actions\CRM\Prospect\Mailshots\RunProspectMailshotScheduled;
use App\Actions\CRM\Prospect\Mailshots\RunProspectMailshotSecondWave;
use App\Actions\CRM\WebUserPasswordReset\PurgeWebUserPasswordReset;
use App\Actions\Web\Website\PruneWebsiteConversionEvents;
use App\Actions\Web\Website\PruneWebsitePageViews;
use App\Actions\Web\Website\PruneWebsiteVisitors;
use App\Actions\Fulfilment\ConsolidateRecurringBills;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomersHydrateStatus;
use App\Actions\Fulfilment\UpdateCurrentRecurringBillsTemporalAggregates;
use App\Actions\Helpers\Intervals\ResetDailyIntervals;
use App\Actions\Helpers\Intervals\ResetMonthlyIntervals;
use App\Actions\Helpers\Intervals\ResetQuarterlyIntervals;
use App\Actions\Helpers\Intervals\ResetWeeklyIntervals;
use App\Actions\Helpers\Intervals\ResetYearIntervals;
use App\Actions\Helpers\Isdoc\DeleteTempIsdoc;
use App\Actions\HydrateHealthRank;
use App\Actions\Retina\Dropshipping\Portfolio\PurgeDownloadPortfolioCustomerSalesChannel;
use App\Actions\Transfers\FetchStack\ProcessFetchStacks;
use App\Actions\Web\Website\SaveWebsitesSitemap;
use App\Traits\LoggableSchedule;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    use LoggableSchedule;

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('horizon:snapshot')->everyFiveMinutes()->onOneServer();
        $schedule->command('cloudflare:reload')->daily()->onOneServer();
        $schedule->command('domain:check-cloudflare-status')->hourly()->onOneServer();


        if (config('app.master')) {
            $this->logSchedule(
                $schedule->job(RunNewsletterScheduled::makeJob())->everyMinute()->timezone('UTC')->onOneServer()->withoutOverlapping()->sentryMonitor(
                    monitorSlug: 'RunNewsletterScheduled',
                ),
                name: 'RunNewsletterScheduled',
                type: 'job',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->job(RunMailshotScheduled::makeJob())->everyMinute()->timezone('UTC')->onOneServer()->withoutOverlapping()->sentryMonitor(
                    monitorSlug: 'RunMailshotScheduled',
                ),
                name: 'RunMailshotScheduled',
                type: 'job',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->job(RunMailshotSecondWave::makeJob())->everyMinute()->timezone('UTC')->onOneServer()->withoutOverlapping()->sentryMonitor(
                    monitorSlug: 'RunMailshotSecondWave',
                ),
                name: 'RunMailshotSecondWave',
                type: 'job',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->job(RunProspectMailshotScheduled::makeJob())->everyMinute()->timezone('UTC')->onOneServer()->withoutOverlapping()->sentryMonitor(
                    monitorSlug: 'RunProspectMailshotScheduled',
                ),
                name: 'RunProspectMailshotScheduled',
                type: 'job',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->job(RunProspectMailshotSecondWave::makeJob())->everyMinute()->timezone('UTC')->onOneServer()->withoutOverlapping()->sentryMonitor(
                    monitorSlug: 'RunProspectMailshotSecondWave',
                ),
                name: 'RunProspectMailshotSecondWave',
                type: 'job',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->job(RunMailshotTrackingUpdates::makeJob())->hourly()->timezone('UTC')->onOneServer()->withoutOverlapping()->sentryMonitor(
                    monitorSlug: 'RunMailshotTrackingUpdates',
                ),
                name: 'RunMailshotTrackingUpdates',
                type: 'job',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->command('run:current_stock_history')->twiceDailyAt(0, 22, 5)->onOneServer()->withoutOverlapping()->timezone('UTC')->sentryMonitor(
                    monitorSlug: 'RunCurrentStockHistory',
                ),
                name: 'RunCurrentStockHistory',
                type: 'command',
                scheduledAt: now()->format('H:i')
            );


            $this->logSchedule(
                $schedule->job(FulfilmentCustomersHydrateStatus::makeJob())->dailyAt('00:00')->onOneServer()->timezone('UTC')->sentryMonitor(
                    monitorSlug: 'FulfilmentCustomersHydrateStatus'
                ),
                name: 'FulfilmentCustomersHydrateStatus',
                type: 'job',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->job(ResetYearIntervals::makeJob())->yearlyOn(1, 1, '00:00')->timezone('UTC')->onOneServer()->sentryMonitor(
                    monitorSlug: 'ResetYearIntervals'
                ),
                name: 'ResetYearIntervals',
                type: 'job',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->job(ResetMonthlyIntervals::makeJob())->monthlyOn(1, '00:00')->timezone('UTC')->onOneServer()->sentryMonitor(
                    monitorSlug: 'ResetMonthlyIntervals',
                ),
                name: 'ResetMonthlyIntervals',
                type: 'job',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->job(ResetQuarterlyIntervals::makeJob())->quarterlyOn(1, '00:00')->timezone('UTC')->onOneServer()->sentryMonitor(
                    monitorSlug: 'ResetQuarterlyIntervals',
                ),
                name: 'ResetQuarterlyIntervals',
                type: 'job',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->job(ResetWeeklyIntervals::makeJob())->weeklyOn(1, '00:00')->timezone('UTC')->onOneServer()->sentryMonitor(
                    monitorSlug: 'ResetWeeklyIntervals',
                ),
                name: 'ResetWeeklyIntervals',
                type: 'job',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->job(ResetDailyIntervals::makeJob())->dailyAt('00:00')->timezone('UTC')->onOneServer()->sentryMonitor(
                    monitorSlug: 'ResetDailyIntervals',
                ),
                name: 'ResetDailyIntervals',
                type: 'job',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->job(UpdateCurrentRecurringBillsTemporalAggregates::makeJob())->dailyAt('00:00')->timezone('UTC')->onOneServer()->sentryMonitor(
                    monitorSlug: 'UpdateCurrentRecurringBillsTemporalAggregates',
                ),
                name: 'UpdateCurrentRecurringBillsTemporalAggregates',
                type: 'job',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->command('fetch:orders -w full -B')->everyFiveMinutes()->timezone('UTC')->onOneServer()->withoutOverlapping()->sentryMonitor(
                    monitorSlug: 'FetchOrdersInBasket',
                ),
                name: 'FetchOrdersInBasket',
                type: 'job',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->command('fetch:credits -N')->everyTenMinutes()->timezone('UTC')->onOneServer()->withoutOverlapping(),
                name: 'FetchCredits',
                type: 'job',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->command('fetch:stock_locations aw')->dailyAt('02:30')->timezone('UTC')->onOneServer()->withoutOverlapping()->sentryMonitor(
                    monitorSlug: 'FetchAuroraStockLocationsAW',
                ),
                name: 'FetchAuroraStockLocationsAW',
                type: 'command',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->command('fetch:stock_locations sk')->dailyAt('02:45')->timezone('UTC')->onOneServer()->withoutOverlapping()->sentryMonitor(
                    monitorSlug: 'FetchAuroraStockLocationsSK',
                ),
                name: 'FetchAuroraStockLocationsSK',
                type: 'command',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->command('fetch:stock_locations es')->dailyAt('03:00')->timezone('UTC')->onOneServer()->withoutOverlapping()->sentryMonitor(
                    monitorSlug: 'FetchAuroraStockLocationsES',
                ),
                name: 'FetchAuroraStockLocationsES',
                type: 'command',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->command('fetch:stock_locations aroma')->dailyAt('3:15')->timezone('UTC')->onOneServer()->withoutOverlapping()->sentryMonitor(
                    monitorSlug: 'FetchAuroraStockLocationsAroma',
                ),
                name: 'FetchAuroraStockLocationsAroma',
                type: 'command',
                scheduledAt: now()->format('H:i')
            );


            $this->logSchedule(
                $schedule->command('fetch:stock_movements -N -D 2')->everyFifteenMinutes()->withoutOverlapping()->timezone('UTC')->onOneServer()->sentryMonitor(
                    monitorSlug: 'FetchAuroraStockMovements',
                ),
                name: 'FetchAuroraStockMovements',
                type: 'command',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->command('fetch:dispatched_emails -w full -D 2 -N')->everySixHours(15)->withoutOverlapping()->timezone('UTC')->onOneServer()->sentryMonitor(
                    monitorSlug: 'FetchDispatchedEmails',
                ),
                name: 'FetchDispatchedEmails',
                type: 'command',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->command('fetch:email_tracking_events -N -D 2')->twiceDaily(4, 17)->timezone('UTC')->onOneServer()->withoutOverlapping()->sentryMonitor(
                    monitorSlug: 'FetchEmailTrackingEvents',
                ),
                name: 'FetchEmailTrackingEvents',
                type: 'command',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->command('fetch:ebay-orders')->everyTwoHours()->withoutOverlapping()->onOneServer()->sentryMonitor(
                    monitorSlug: 'FetchEbayOrders',
                ),
                name: 'FetchEbayOrders',
                type: 'command',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->command('fetch:woo-orders')->everyTwoHours()->withoutOverlapping()->onOneServer()->sentryMonitor(
                    monitorSlug: 'FetchWooOrders',
                ),
                name: 'FetchWooOrders',
                type: 'command',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->command('woo:ping_active_channel')->everySixHours()->withoutOverlapping()->onOneServer()->sentryMonitor(
                    monitorSlug: 'PingActiveWooChannel',
                ),
                name: 'PingActiveWooChannel',
                type: 'command',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->command('ebay:ping')->daily()->withoutOverlapping()->onOneServer()->sentryMonitor(
                    monitorSlug: 'CheckAllEbayChannels',
                ),
                name: 'CheckAllEbayChannels',
                type: 'command',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->command('woo:update-inventory')->hourly()->withoutOverlapping()->onOneServer()->sentryMonitor(
                    monitorSlug: 'UpdateWooStockInventories',
                ),
                name: 'UpdateWooStockInventories',
                type: 'command',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->command('ebay:update-inventory')->everyTwoHours()->withoutOverlapping()->onOneServer()->sentryMonitor(
                    monitorSlug: 'UpdateInventoryInEbayPortfolio',
                ),
                name: 'UpdateInventoryInEbayPortfolio',
                type: 'command',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->command('shopify:update-inventory')->everySixHours()->withoutOverlapping()->onOneServer()->sentryMonitor(
                    monitorSlug: 'UpdateInventoryInShopifyPortfolio',
                ),
                name: 'UpdateInventoryInShopifyPortfolio',
                type: 'command',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->command('shopify:check_portfolios grp aw')->dailyAt('03:00')->timezone('UTC')->onOneServer()->sentryMonitor(
                    monitorSlug: 'CheckShopifyPortfolios',
                ),
                name: 'CheckShopifyPortfolios',
                type: 'command',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->command('shop-external:check-all-shop-connections')->everyTwoHours()->withoutOverlapping()->onOneServer()->sentryMonitor(
                    monitorSlug: 'CheckExternalShopConnection',
                ),
                name: 'CheckExternalShopConnection',
                type: 'command',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->command('platform-logs:delete')->daily()->onOneServer()->sentryMonitor(
                    monitorSlug: 'PlatformDeletePortfolioLogs',
                ),
                name: 'PlatformDeletePortfolioLogs',
                type: 'command',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->command('delete:debug-webhook 10')->daily()->onOneServer()->sentryMonitor(
                    monitorSlug: 'DeleteDebugWebhookPeriodically',
                ),
                name: 'DeleteDebugWebhookPeriodically',
                type: 'command',
                scheduledAt: now()->format('H:i')
            );


            $this->logSchedule(
                $schedule->job(ProcessFetchStacks::makeJob())->everyMinute()->withoutOverlapping()->timezone('UTC')->onOneServer()->sentryMonitor(
                    monitorSlug: 'ProcessFetchStacks',
                ),
                name: 'ProcessFetchStacks',
                type: 'job',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->job(ConsolidateRecurringBills::makeJob())->dailyAt('20:00')->timezone('UTC')->onOneServer()->sentryMonitor(
                    monitorSlug: 'ConsolidateRecurringBills',
                ),
                name: 'ConsolidateRecurringBills',
                type: 'job',
                scheduledAt: now()->format('H:i')
            );


            $this->logSchedule(
                $schedule->job(RunReorderRemainderEmailBulkRuns::makeJob())->dailyAt('15:00')->timezone('UTC')->onOneServer()->sentryMonitor(
                    monitorSlug: 'RunReorderRemainderEmailBulkRuns',
                ),
                name: 'RunReorderRemainderEmailBulkRuns',
                type: 'job',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->job(RunBackInStockEmailBulkRuns::makeJob())->dailyAt('15:00')->timezone('UTC')->onOneServer()->sentryMonitor(
                    monitorSlug: 'BackToStockHydrateEmailBulkRuns',
                ),
                name: 'BackToStockHydrateEmailBulkRuns',
                type: 'job',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->job(GetFaireOrdersAllShops::makeJob())->everyFifteenMinutes()->withoutOverlapping()->timezone('UTC')->onOneServer()->sentryMonitor(
                    monitorSlug: 'GetFaireOrdersAllShops',
                ),
                name: 'GetFaireOrdersAllShops',
                type: 'job',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->job(GetFaireProductsAllShops::makeJob())->twiceDailyAt(12, 17)->withoutOverlapping()->timezone('UTC')->onOneServer()->sentryMonitor(
                    monitorSlug: 'GetFaireProductsAllShops',
                ),
                name: 'GetFaireProductsAllShops',
                type: 'job',
                scheduledAt: now()->format('H:i')
            );


            $this->logSchedule(
                $schedule->job(RunPriceChangeNotificationEmailBulkRuns::makeJob())->dailyAt('15:00')->timezone('UTC')->withoutOverlapping()->onOneServer()->sentryMonitor(
                    monitorSlug: 'RunPriceChangeNotificationEmailBulkRuns',
                ),
                name: 'RunPriceChangeNotificationEmailBulkRuns',
                type: 'job',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->job(RunBasketLowStockEmailBulkRuns::makeJob())->hourly()->timezone('UTC')->withoutOverlapping()->onOneServer()->sentryMonitor(
                    monitorSlug: 'RunBasketLowStockEmailBulkRuns',
                ),
                name: 'RunBasketLowStockEmailBulkRuns',
                type: 'job',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->job(PurgeDownloadPortfolioCustomerSalesChannel::makeJob())->everyMinute()->withoutOverlapping()->timezone('UTC')->onOneServer()->sentryMonitor(
                    monitorSlug: 'PurgeDownloadPortfolioCustomerSalesChannel',
                ),
                name: 'PurgeDownloadPortfolioCustomerSalesChannel',
                type: 'job',
                scheduledAt: now()->format('H:i')
            );
            $this->logSchedule(
                $schedule->command('dispatched-email:clean-provider-dispatch-id')->dailyAt('3:30')->timezone('UTC')->onOneServer()->sentryMonitor(
                    monitorSlug: 'CleanProviderDispatchID',
                ),
                name: 'CleanProviderDispatchID',
                type: 'command',
                scheduledAt: now()->format('H:i')
            );


            $this->logSchedule(
                $schedule->command(' offer:update_status_from_dates')->hourly()->timezone('UTC')->onOneServer()->sentryMonitor(
                    monitorSlug: 'OfferUpdateStatusFromDates',
                ),
                name: 'OfferUpdateStatusFromDates',
                type: 'command',
                scheduledAt: now()->format('H:i')
            );
        }

        if (config('app.slave')) {
            $this->logSchedule(
                $schedule->command('data_feeds:save')->hourly()->timezone('UTC')->onOneServer()->sentryMonitor(
                    monitorSlug: 'SaveDataFeeds',
                ),
                name: 'SaveDataFeeds',
                type: 'job',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->job(PruneWebsiteVisitors::makeJob())->dailyAt('03:45')->timezone('UTC')->onOneServer()->sentryMonitor(
                    monitorSlug: 'PruneWebsiteVisitors',
                ),
                name: 'PruneWebsiteVisitors',
                type: 'job',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->job(SaveWebsitesSitemap::makeJob())->dailyAt('19:00')->timezone('UTC')->onOneServer()->sentryMonitor(
                    monitorSlug: 'SaveWebsitesSitemap',
                ),
                name: 'SaveWebsitesSitemap',
                type: 'job',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->command('clone:aurora_vol_gr_offers sk eu')->twiceDailyAt(12, 18)->timezone('UTC')->onOneServer()->sentryMonitor(
                    monitorSlug: 'CloneAuroraVolGrOffers',
                ),
                name: 'CloneAuroraVolGrOffers',
                type: 'command',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->job(PurgeWebUserPasswordReset::makeJob())->dailyAt('03:00')->timezone('UTC')->onOneServer()->sentryMonitor(
                    monitorSlug: 'PurgeWebUserPasswordReset',
                ),
                name: 'PurgeWebUserPasswordReset',
                type: 'job',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->job(DeleteTempIsdoc::makeJob())->dailyAt('00:00')->timezone('UTC')->onOneServer()->sentryMonitor(
                    monitorSlug: 'DeleteTempIsdoc',
                ),
                name: 'DeleteTempIsdoc',
                type: 'job',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->job(HydrateHealthRank::makeJob())->dailyAt('04:00')->timezone('UTC')->onOneServer()->sentryMonitor(
                    monitorSlug: 'HydrateHealthRank',
                ),
                name: 'HydrateHealthRank',
                type: 'job',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->job(PruneCustomerWebActivities::makeJob())->dailyAt('03:30')->timezone('UTC')->onOneServer()->sentryMonitor(
                    monitorSlug: 'PruneCustomerWebActivities',
                ),
                name: 'PruneCustomerWebActivities',
                type: 'job',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->job(PruneWebsitePageViews::makeJob())->dailyAt('03:35')->timezone('UTC')->onOneServer()->sentryMonitor(
                    monitorSlug: 'PruneWebsitePageViews',
                ),
                name: 'PruneWebsitePageViews',
                type: 'job',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->job(PruneWebsiteConversionEvents::makeJob())->dailyAt('03:40')->timezone('UTC')->onOneServer()->sentryMonitor(
                    monitorSlug: 'PruneWebsiteConversionEvents',
                ),
                name: 'PruneWebsiteConversionEvents',
                type: 'job',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->command('hydrate:customers-clv')->dailyAt('01:00')->timezone('UTC')->onOneServer()->sentryMonitor(
                    monitorSlug: 'HydrateCustomersClv',
                ),
                name: 'HydrateCustomersClv',
                type: 'command',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->command('hydrate:customers-tag')->dailyAt('02:00')->timezone('UTC')->onOneServer()->sentryMonitor(
                    monitorSlug: 'HydrateCustomersTag',
                ),
                name: 'HydrateCustomersTag',
                type: 'command',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->command('hydrate:best_seller')->dailyAt('03:00')->timezone('UTC')->onOneServer()->sentryMonitor(
                    monitorSlug: 'HydrateBestSellerProduct',
                ),
                name: 'HydrateBestSellerProduct',
                type: 'command',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->command('hydrate:mismatch_detected')->weeklyOn(1, '03:00')->timezone('UTC')->onOneServer()->sentryMonitor(
                    monitorSlug: 'HydrateMismatchDetected',
                ),
                name: 'HydrateMismatchDetected',
                type: 'command',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->command('ui:recache-user-props')->weeklyOn(1, '06:00')->timezone('UTC')->onOneServer()->sentryMonitor(
                    monitorSlug: 'RecacheUserUiProps',
                ),
                name: 'RecacheUserUiProps',
                type: 'command',
                scheduledAt: now()->format('H:i')
            );


            $schedule->command('queue:prune-failed --hours=168')->daily()->onOneServer();
        }

        $this->logSchedule(
            $schedule->command('hydrate:fulfilments')->dailyAt('00:30')->timezone('UTC')->onOneServer()->withoutOverlapping()->sentryMonitor(
                monitorSlug: 'HydrateFulfilments',
            ),
            name: 'HydrateFulfilments',
            type: 'command',
            scheduledAt: now()->format('H:i')
        );
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
