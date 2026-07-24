<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Sept 2025 03:58:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Console;

use App\Actions\Accounting\Invoice\RedoDailyInvoiceTimeSeries;
use App\Actions\Accounting\Payment\CheckoutCom\SweepStuckCheckoutComPaymentApiPoints;
use App\Actions\Catalogue\Shop\External\Faire\GetFaireOrdersAllShops;
use App\Actions\Catalogue\Shop\External\Faire\GetFaireProductsAllShops;
use App\Actions\Comms\Mailshot\RunMailshotScheduled;
use App\Actions\Comms\Mailshot\RunMailshotSecondWave;
use App\Actions\Comms\Mailshot\RunMailshotTrackingUpdates;
use App\Actions\Comms\Mailshot\RunNewsletterScheduled;
use App\Actions\Comms\Outbox\BackInStockNotification\RunBackInStockEmailBulkRuns;
use App\Actions\Comms\Outbox\GoldRewardReminder\RunGoldRewardReminderEmailBulkRuns;
use App\Actions\Comms\Outbox\LowStockInBasket\RunBasketLowStockEmailBulkRuns;
use App\Actions\Comms\Outbox\OutOfStockInOrder\RunOutOfStockInOrderEmailBulkRuns;
use App\Actions\Ordering\CheckoutAbandonment\RunCheckoutAbandonmentScan;
use App\Actions\Comms\Outbox\PriceChangeNotification\RunPriceChangeNotificationEmailBulkRuns;
use App\Actions\Comms\Outbox\ReorderRemainder\RunReorderRemainderEmailBulkRuns;
use App\Actions\Comms\Outbox\ReviewReminder\RunReviewReminderEmailBulkRuns;
use App\Actions\CRM\Customer\HydrateCustomersClv;
use App\Actions\CRM\Customer\PruneCustomerWebActivities;
use App\Actions\CRM\Prospect\Mailshots\RunProspectMailshotScheduled;
use App\Actions\CRM\Prospect\Mailshots\RunProspectMailshotSecondWave;
use App\Actions\CRM\WebUserPasswordReset\PurgeWebUserPasswordReset;
use App\Actions\DevOps\WebsiteHealthLog\MonitorWebsitesUptime;
use App\Actions\Discounts\Offer\ActivateScheduledOffers;
use App\Actions\Web\Website\Cloudflare\FetchFirewallBlockedCountryEvents;
use App\Actions\Reviews\AutoPublishReviews;
use App\Actions\Dropshipping\Ebay\Orders\FetchEbayOrders;
use App\Actions\Dropshipping\Shopify\Product\UpdateShopifyInventory;
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
use App\Actions\Web\Crawl\PurgeStaleCrawls;
use App\Actions\Web\Website\Analytics\RecordVarnishHitRatio;
use App\Actions\Web\Website\Analytics\RecordVarnishMemoryUsage;
use App\Actions\Web\Website\PruneWebsiteConversionEvents;
use App\Actions\Web\Website\PruneWebsitePageViews;
use App\Actions\Web\Website\PruneWebsiteVisitors;
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

        if (config('app.master')) {
            $this->logSchedule(
                $schedule->job(ActivateScheduledOffers::makeJob())->hourly()->withoutOverlapping()->onOneServer()->sentryMonitor(
                    monitorSlug: 'ActivateScheduledOffers',
                ),
                name: 'ActivateScheduledOffers',
                type: 'job',
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

            $this->logSchedule(
                $schedule->job(SweepStuckCheckoutComPaymentApiPoints::makeJob())->everyThirtyMinutes()->withoutOverlapping()->onOneServer()->sentryMonitor(
                    monitorSlug: 'SweepStuckCheckoutComPaymentApiPoints',
                ),
                name: 'SweepStuckCheckoutComPaymentApiPoints',
                type: 'job',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->job(RecordVarnishHitRatio::makeJob())->everyMinute()->timezone('UTC')->withoutOverlapping()->sentryMonitor(
                    monitorSlug: 'RecordVarnishHitRatio',
                ),
                name: 'RecordVarnishHitRatio',
                type: 'job',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->job(RecordVarnishMemoryUsage::makeJob())->everyMinute()->timezone('UTC')->withoutOverlapping()->sentryMonitor(
                    monitorSlug: 'RecordVarnishMemoryUsage',
                ),
                name: 'RecordVarnishMemoryUsage',
                type: 'job',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->job(MonitorWebsitesUptime::makeJob())->everyTwoMinutes()->withoutOverlapping()->onOneServer()->sentryMonitor(
                    monitorSlug: 'MonitorWebsitesUptime',
                ),
                name: 'MonitorWebsitesUptime',
                type: 'command',
                scheduledAt: now()->format('H:i')
            );

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
                $schedule->job(RunOutOfStockInOrderEmailBulkRuns::makeJob())->hourly()->timezone('UTC')->onOneServer()->withoutOverlapping()->sentryMonitor(
                    monitorSlug: 'RunOutOfStockInOrderEmailBulkRuns',
                ),
                name: 'RunOutOfStockInOrderEmailBulkRuns',
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
                $schedule->command('fetch:orders aroma -w full -B')->everyFiveMinutes()->timezone('UTC')->onOneServer()->withoutOverlapping()->sentryMonitor(
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
                $schedule->command('fetch:stock_locations aroma')->dailyAt('3:15')->timezone('UTC')->onOneServer()->withoutOverlapping()->sentryMonitor(
                    monitorSlug: 'FetchAuroraStockLocationsAroma',
                ),
                name: 'FetchAuroraStockLocationsAroma',
                type: 'command',
                scheduledAt: now()->format('H:i')
            );


            $this->logSchedule(
                $schedule->command('fetch:stock_movements aroma -N -D 2')->everyTenMinutes()->withoutOverlapping()->timezone('UTC')->onOneServer()->sentryMonitor(
                    monitorSlug: 'FetchAuroraStockMovementsAroma',
                ),
                name: 'FetchAuroraStockMovements',
                type: 'command',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->command('fetch:stock_movements aw -N -D 2')->everyMinute()->withoutOverlapping()->timezone('UTC')->onOneServer()->sentryMonitor(
                    monitorSlug: 'FetchAuroraStockMovements',
                ),
                name: 'FetchAuroraStockMovementsAw',
                type: 'command',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->command('fetch:stock_movements sk  -N -D 2')->everyMinute()->withoutOverlapping()->timezone('UTC')->onOneServer()->sentryMonitor(
                    monitorSlug: 'FetchAuroraStockMovements',
                ),
                name: 'FetchAuroraStockMovementsSk',
                type: 'command',
                scheduledAt: now()->format('H:i')
            );


            $this->logSchedule(
                $schedule->command('fetch:stock_movements es -N -D 2')->everyMinute()->withoutOverlapping()->timezone('UTC')->onOneServer()->sentryMonitor(
                    monitorSlug: 'FetchAuroraStockMovementsEs',
                ),
                name: 'FetchAuroraStockMovements',
                type: 'command',
                scheduledAt: now()->format('H:i')
            );


            $this->logSchedule(
                $schedule->job(FetchEbayOrders::makeJob())->hourly()->between('6:00', '17:00')->withoutOverlapping()->timezone('UTC')->onOneServer()->sentryMonitor(
                    monitorSlug: 'FetchEbayOrders',
                ),
                name: 'FetchEbayOrders',
                type: 'command',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->job(FetchEbayOrders::makeJob())->everyFourHours(30)->unlessBetween('6:00', '17:00')->withoutOverlapping()->timezone('UTC')->onOneServer()->sentryMonitor(
                    monitorSlug: 'FetchEbayOrdersAfterHours',
                ),
                name: 'FetchEbayOrdersAfterHours',
                type: 'job',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->command('allegro:fetch-orders')->everyTwoHours()->withoutOverlapping()->onOneServer()->sentryMonitor(
                    monitorSlug: 'FetchAllegroOrders',
                ),
                name: 'FetchAllegroOrders',
                type: 'command',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->command('allegro:fetch-cancelled-orders')->everyTwoHours()->withoutOverlapping()->onOneServer()->sentryMonitor(
                    monitorSlug: 'FetchCancelledAllegroOrders',
                ),
                name: 'FetchCancelledAllegroOrders',
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
                $schedule->command('woo:update-inventory')->everyThreeHours()->withoutOverlapping()->onOneServer()->sentryMonitor(
                    monitorSlug: 'UpdateWooStockInventories',
                ),
                name: 'UpdateWooStockInventories',
                type: 'command',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->command('dropshipping:tiktok:product:inventory:update')->hourly()->withoutOverlapping()->onOneServer()->sentryMonitor(
                    monitorSlug: 'UpdateTiktokInventory',
                ),
                name: 'UpdateTiktokInventory',
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
                $schedule->job(UpdateShopifyInventory::makeJob())->everySixHours()->withoutOverlapping()->timezone('UTC')->onOneServer()->sentryMonitor(
                    monitorSlug: 'UpdateShopifyInventory',
                ),
                name: 'UpdateShopifyInventory',
                type: 'job',
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
                $schedule->job(ConsolidateRecurringBills::makeJob())->dailyAt('17:00')->timezone('UTC')->onOneServer()->sentryMonitor(
                    monitorSlug: 'ConsolidateRecurringBills',
                ),
                name: 'ConsolidateRecurringBills',
                type: 'job',
                scheduledAt: now()->format('H:i')
            );


            // $this->logSchedule(
            //     $schedule->job(RunReorderRemainderEmailBulkRuns::makeJob())->dailyAt('15:00')->timezone('UTC')->onOneServer()->sentryMonitor(
            //         monitorSlug: 'RunReorderRemainderEmailBulkRuns',
            //     ),
            //     name: 'RunReorderRemainderEmailBulkRuns',
            //     type: 'job',
            //     scheduledAt: now()->format('H:i')
            // );

            $this->logSchedule(
                $schedule->job(RunGoldRewardReminderEmailBulkRuns::makeJob())->dailyAt('15:00')->withoutOverlapping()->timezone('UTC')->onOneServer()->sentryMonitor(
                    monitorSlug: 'RunGoldRewardReminderEmailBulkRuns',
                ),
                name: 'RunGoldRewardReminderEmailBulkRuns',
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
                $schedule->job(RunCheckoutAbandonmentScan::makeJob())->hourly()->timezone('UTC')->withoutOverlapping()->onOneServer()->sentryMonitor(
                    monitorSlug: 'RunCheckoutAbandonmentScan',
                ),
                name: 'RunCheckoutAbandonmentScan',
                type: 'job',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->job(RunReviewReminderEmailBulkRuns::makeJob())->dailyAt('15:00')->timezone('UTC')->withoutOverlapping()->onOneServer()->sentryMonitor(
                    monitorSlug: 'RunReviewReminderEmailBulkRuns',
                ),
                name: 'RunReviewReminderEmailBulkRuns',
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
        }

        if (config('app.slave')) {
            $this->logSchedule(
                $schedule->job(RunMailshotTrackingUpdates::makeJob())->hourly()->timezone('UTC')->onOneServer()->withoutOverlapping()->sentryMonitor(
                    monitorSlug: 'RunMailshotTrackingUpdates',
                ),
                name: 'RunMailshotTrackingUpdates',
                type: 'job',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->job(PurgeStaleCrawls::makeJob())->everyTenMinutes()->timezone('UTC')->withoutOverlapping()->sentryMonitor(
                    monitorSlug: 'PurgeStaleCrawls',
                ),
                name: 'PurgeStaleCrawls',
                type: 'job',
                scheduledAt: now()->format('H:i')
            );

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
                $schedule->job(RedoDailyInvoiceTimeSeries::makeJob())->dailyAt('22:00')->timezone('UTC')->onOneServer()->withoutOverlapping()->sentryMonitor(
                    monitorSlug: 'RedoDailyInvoiceTimeSeries',
                ),
                name: 'RedoDailyInvoiceTimeSeries',
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
                $schedule->job(HydrateCustomersClv::makeJob())->dailyAt('01:00')->timezone('UTC')->onOneServer()->sentryMonitor(
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
                    monitorSlug: 'BreakUserUiProps',
                ),
                name: 'BreakUserUiProps',
                type: 'command',
                scheduledAt: now()->format('H:i')
            );


            $schedule->command('queue:prune-failed --hours=168')->daily()->onOneServer();

            $this->logSchedule(
                $schedule->command('hydrate:fulfilments')->dailyAt('00:30')->timezone('UTC')->onOneServer()->withoutOverlapping()->sentryMonitor(
                    monitorSlug: 'HydrateFulfilments',
                ),
                name: 'HydrateFulfilments',
                type: 'command',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->command('hydrate:stored_items')->dailyAt('00:40')->timezone('UTC')->onOneServer()->withoutOverlapping()->sentryMonitor(
                    monitorSlug: 'HydrateStoredItems',
                ),
                name: 'HydrateStoredItems',
                type: 'command',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->command('hydrate:pallet_returns')->dailyAt('00:50')->timezone('UTC')->onOneServer()->withoutOverlapping()->sentryMonitor(
                    monitorSlug: 'HydratePalletReturns',
                ),
                name: 'HydratePalletReturns',
                type: 'command',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->command('hydrate:pallet_stored_items')->dailyAt('01:00')->timezone('UTC')->onOneServer()->withoutOverlapping()->sentryMonitor(
                    monitorSlug: 'HydratePalletStoredItems',
                ),
                name: 'HydratePalletStoredItems',
                type: 'command',
                scheduledAt: now()->format('H:i')
            );
            $this->logSchedule(
                $schedule->command('charge:hydrate-stats')->dailyAt('02:00')->timezone('UTC')->onOneServer()->withoutOverlapping()->sentryMonitor(
                    monitorSlug: 'ChargeHydrateStats',
                ),
                name: 'ChargeHydrateStats',
                type: 'command',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->command('leave:generate-balances')->dailyAt('01:00')->timezone('UTC')->onOneServer()->withoutOverlapping()->sentryMonitor(
                    monitorSlug: 'LeaveGenerateBalances',
                ),
                name: 'LeaveGenerateBalances',
                type: 'command',
                scheduledAt: now()->format('H:i')
            );


            $this->logSchedule(
                $schedule->job(AutoPublishReviews::makeJob())->hourly()->timezone('UTC')->onOneServer()->withoutOverlapping()->sentryMonitor(
                    monitorSlug: 'AutoPublishReviews',
                ),
                name: 'AutoPublishReviews',
                type: 'job',
                scheduledAt: now()->format('H:i')
            );

            $this->logSchedule(
                $schedule->job(FetchFirewallBlockedCountryEvents::makeJob())->hourly()->withoutOverlapping()->onOneServer()->sentryMonitor(
                    monitorSlug: 'FetchFirewallBlockedCountryEvents',
                ),
                name: 'FetchFirewallBlockedCountryEvents',
                type: 'job',
                scheduledAt: now()->format('H:i')
            );


            $this->logSchedule(
                $schedule->command('outboxes:redo_time_series --from='.now()->subDays()->format('Y-m-d').' --to='.now()->format('Y-m-d').' --async')
                    ->dailyAt('16:00')
                    ->timezone('UTC')->onOneServer()->withoutOverlapping()->sentryMonitor(
                        monitorSlug: 'OutboxRedoTimeSeries',
                    ),
                name: 'OutboxRedoTimeSeries',
                type: 'command',
                scheduledAt: now()->format('H:i')
            );
        }
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
