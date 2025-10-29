<?php

namespace App\Actions\Dropshipping\Platform\Shop\Hydrators;

use App\Actions\Traits\WithIntervalsAggregators;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\PlatformShopSalesIntervals;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydratePlatformSalesIntervalsNewPortfolios implements ShouldBeUnique
{
    use AsAction;
    use WithIntervalsAggregators;

    public function handle(Shop $shop, ?array $intervals = null, ?array $doPreviousPeriods = null): void
    {
        $platformIds = Portfolio
            ::where('item_type', class_basename(Product::class))
            ->whereHas('item', function ($query) use ($shop) {
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
            $queryBase = Portfolio::where('item_type', class_basename(Product::class))
                ->leftJoin('products', 'portfolios.item_id', '=', 'products.id')
                ->where('platform_id', $platformId)
                ->where('products.shop_id', $shop->id)
                ->selectRaw('count(distinct portfolios.item_id) as sum_aggregate');

            $stats = $this->getIntervalsData(
                stats: [],
                queryBase: $queryBase,
                statField: 'new_portfolios_',
                dateField: 'portfolios.created_at',
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
