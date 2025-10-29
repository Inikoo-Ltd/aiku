<?php

namespace App\Actions\Dropshipping\Platform\Shop\Hydrators;

use App\Actions\Traits\WithIntervalsAggregators;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\PlatformShopSalesIntervals;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydratePlatformSalesIntervalsNewCustomers implements ShouldBeUnique
{
    use AsAction;
    use WithIntervalsAggregators;

    public function handle(Shop $shop, ?array $intervals = null, ?array $doPreviousPeriods = null): void
    {
        $platformIds = CustomerSalesChannel::whereHas('customer', function ($query) use ($shop) {
                $query->where('shop_id', $shop->id);
            })
            ->select('platform_id')
            ->distinct()
            ->pluck('platform_id')
            ->filter();

        if ($platformIds->isEmpty()) {
            return;
        }

        foreach ($platformIds as $platformId) {
            $queryBase = CustomerSalesChannel::leftJoin('customers', 'customer_sales_channels.customer_id', '=', 'customers.id')
                ->where('platform_id', $platformId)
                ->where('customers.shop_id', $shop->id)
                ->selectRaw('count(distinct customer_sales_channels.customer_id) as sum_aggregate');

            $stats = $this->getIntervalsData(
                stats: [],
                queryBase: $queryBase,
                statField: 'new_customers_',
                dateField: 'customer_sales_channels.created_at',
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            );

            PlatformShopSalesIntervals::updateOrCreate(
                [
                    'platform_id' => $platformId,
                    'shop_id'     => $shop->id
                ],
                $stats
            );
        }
    }
}
