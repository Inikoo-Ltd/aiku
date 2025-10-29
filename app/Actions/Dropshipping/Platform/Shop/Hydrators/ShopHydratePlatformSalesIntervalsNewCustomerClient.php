<?php

namespace App\Actions\Dropshipping\Platform\Shop\Hydrators;

use App\Actions\Traits\WithIntervalsAggregators;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\PlatformShopSalesIntervals;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydratePlatformSalesIntervalsNewCustomerClient implements ShouldBeUnique
{
    use AsAction;
    use WithIntervalsAggregators;

    public function handle(Shop $shop, ?array $intervals = null, ?array $doPreviousPeriods = null): void
    {
        $platformIds = CustomerClient
            ::whereHas('customer', function ($query) use ($shop) {
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
            $queryBase = CustomerClient
                ::where('platform_id', $platformId)
                ->whereHas('customer', function ($query) use ($shop) {
                    $query->where('shop_id', $shop->id);
                })
                ->selectRaw('count(*) as sum_aggregate');

            $stats = $this->getIntervalsData(
                stats: [],
                queryBase: $queryBase,
                statField: 'new_customer_client_',
                dateField: 'created_at',
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
