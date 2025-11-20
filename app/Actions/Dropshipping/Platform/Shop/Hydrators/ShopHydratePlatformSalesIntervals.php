<?php

namespace App\Actions\Dropshipping\Platform\Shop\Hydrators;

use App\Actions\Traits\Hydrators\WithIntervalUniqueJob;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Support\Facades\Log;
use Exception;
use Sentry;

// Note: Experimental Data (Need to be checked)
class ShopHydratePlatformSalesIntervals implements ShouldBeUnique
{
    use AsAction;
    use WithIntervalUniqueJob;

    public string $commandSignature = 'hydrate:shop-platform-sales-intervals {shop}';

    public function getJobUniqueId(Shop $shop, ?array $intervals = null, ?array $doPreviousPeriods = null): string
    {
        return $this->getUniqueJobWithInterval($shop, $intervals, $doPreviousPeriods);
    }

    public function asCommand(Command $command): void
    {
        $shop = Shop::where('slug', $command->argument('shop'))->first();

        if (!$shop) {
            $command->error("Shop not found.");
            return;
        }

        $this->handle($shop);
    }

    public function handle(Shop $shop): void
    {
        try {
            ShopHydrateAllPlatformsSalesIntervalsInvoices::run($shop);
            ShopHydrateAllPlatformsSalesIntervalsNewChannels::run($shop);
            ShopHydrateAllPlatformsSalesIntervalsNewCustomers::run($shop);
            ShopHydrateAllPlatformsSalesIntervalsNewPortfolios::run($shop);
            ShopHydrateAllPlatformsSalesIntervalsNewCustomerClient::run($shop);
            ShopHydrateAllPlatformsSalesIntervalsSales::run($shop);
            ShopHydrateAllPlatformsSalesIntervalsSalesOrgCurrency::run($shop);
            ShopHydrateAllPlatformsSalesIntervalsSalesGrpCurrency::run($shop);
        } catch (Exception $e) {
            Log::info("Failed to Hydrate Shop Sales Intervals: " . $e->getMessage());
            Sentry::captureMessage("Failed to Hydrate Shop Sales Intervals to: " . $e->getMessage());
        }
    }
}
