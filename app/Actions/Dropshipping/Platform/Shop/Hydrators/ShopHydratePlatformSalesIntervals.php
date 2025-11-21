<?php

namespace App\Actions\Dropshipping\Platform\Shop\Hydrators;

use App\Actions\Traits\Hydrators\WithIntervalUniqueJob;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

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

    public function handle(Shop $shop, ?array $intervals = null, ?array $doPreviousPeriods = null): void
    {
        ShopHydrateAllPlatformsSalesIntervalsInvoices::run($shop, $intervals, $doPreviousPeriods);
        ShopHydrateAllPlatformsSalesIntervalsNewChannels::run($shop, $intervals, $doPreviousPeriods);
        ShopHydrateAllPlatformsSalesIntervalsNewCustomers::run($shop, $intervals, $doPreviousPeriods);
        ShopHydrateAllPlatformsSalesIntervalsNewPortfolios::run($shop, $intervals, $doPreviousPeriods);
        ShopHydrateAllPlatformsSalesIntervalsNewCustomerClient::run($shop, $intervals, $doPreviousPeriods);
        ShopHydrateAllPlatformsSalesIntervalsSales::run($shop, $intervals, $doPreviousPeriods);
        ShopHydrateAllPlatformsSalesIntervalsSalesOrgCurrency::run($shop, $intervals, $doPreviousPeriods);
        ShopHydrateAllPlatformsSalesIntervalsSalesGrpCurrency::run($shop, $intervals, $doPreviousPeriods);
    }
}
