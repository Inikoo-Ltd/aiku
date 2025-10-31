<?php

namespace App\Actions\Dropshipping\Platform\Shop\Hydrators;

use App\Actions\Traits\Hydrators\WithIntervalUniqueJob;
use App\Actions\Traits\WithIntervalsAggregators;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\PlatformShopSalesIntervals;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydratePlatformSalesIntervalsNewCustomers implements ShouldBeUnique
{
    use AsAction;
    use WithIntervalUniqueJob;
    use WithIntervalsAggregators;

    public function getJobUniqueId(Shop $shop, int $platformId, ?array $intervals = null, ?array $doPreviousPeriods = null): string
    {
        return $this->getUniqueJobWithIntervalFromId($shop->id.'-'.$platformId, $intervals, $doPreviousPeriods);
    }

    public function handle(Shop $shop, int $platformId, ?array $intervals = null, ?array $doPreviousPeriods = null): void
    {
        $platform = Platform::find($platformId);

        if (!$platform || $shop->type != ShopTypeEnum::DROPSHIPPING) {
            return;
        }

        $queryBase = CustomerSalesChannel::leftJoin('customers', 'customer_sales_channels.customer_id', '=', 'customers.id')
            ->where('platform_id', $platformId)
            ->where('customers.shop_id', $shop->id)
            ->selectRaw('count(distinct customer_sales_channels.customer_id) as sum_aggregate');

        $stats = $this->getIntervalsData(
            stats: [],
            queryBase: $queryBase,
            statField: 'new_customers_',
            dateField: 'customers.registered_at',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );

        $platformShopSalesIntervals = PlatformShopSalesIntervals::where('platform_id', $platformId)->where('shop_id', $shop->id)->first();
        $platformShopSalesIntervals?->update($stats);
    }
}
