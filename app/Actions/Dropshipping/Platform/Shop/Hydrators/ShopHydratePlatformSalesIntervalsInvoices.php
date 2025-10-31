<?php

namespace App\Actions\Dropshipping\Platform\Shop\Hydrators;

use App\Actions\Traits\Hydrators\WithIntervalUniqueJob;
use App\Actions\Traits\WithIntervalsAggregators;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\PlatformShopSalesIntervals;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydratePlatformSalesIntervalsInvoices implements ShouldBeUnique
{
    use AsAction;
    use WithIntervalsAggregators;
    use WithIntervalUniqueJob;

    public function getJobUniqueId(int $shopId, int $platformId, ?array $intervals = null, ?array $doPreviousPeriods = null): string
    {
        return $this->getUniqueJobWithIntervalFromId($shopId.'-'.$platformId, $intervals, $doPreviousPeriods);
    }



    public function handle(int $shopId, int $platformId, ?array $intervals = null, ?array $doPreviousPeriods = null): void
    {

        $shop = Shop::find($shopId);
        if (!$shop) {
            return;
        }
        if ($shop->type != ShopTypeEnum::DROPSHIPPING) {
            return;
        }

        $platform = Platform::find($platformId);
        if (!$platform) {
            return;
        }


        $queryBase = Invoice::where('in_process', false)
            ->where('platform_id', $platform->id)
            ->where('shop_id', $shop->id)
            ->where('type', InvoiceTypeEnum::INVOICE)
            ->selectRaw('count(*) as sum_aggregate');

        $stats = $this->getIntervalsData(
            stats: [],
            queryBase: $queryBase,
            statField: 'invoices_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );


        $platformShopSalesIntervals = PlatformShopSalesIntervals::where('platform_id', $platformId)->where('shop_id', $shop->id)->first();
        $platformShopSalesIntervals?->update($stats);


    }



}
