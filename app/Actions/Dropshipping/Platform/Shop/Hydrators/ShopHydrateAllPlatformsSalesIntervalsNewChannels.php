<?php

namespace App\Actions\Dropshipping\Platform\Shop\Hydrators;

use App\Actions\Traits\WithIntervalsAggregators;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateAllPlatformsSalesIntervalsNewChannels implements ShouldBeUnique
{
    use AsAction;
    use WithIntervalsAggregators;

    public function handle(Shop $shop, ?array $intervals = null, ?array $doPreviousPeriods = null): void
    {
        if ($shop->type != ShopTypeEnum::DROPSHIPPING) {
            return;
        }

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
            ShopHydratePlatformSalesIntervalsNewChannels::run($shop, $platformId, $intervals, $doPreviousPeriods);
        }
    }
}
