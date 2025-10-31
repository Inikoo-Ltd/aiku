<?php

namespace App\Actions\Dropshipping\Platform\Shop\Hydrators;

use App\Actions\Traits\WithIntervalsAggregators;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Catalogue\Shop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateAllPlatformsSalesIntervalsSalesOrgCurrency implements ShouldBeUnique
{
    use AsAction;
    use WithIntervalsAggregators;

    public function handle(Shop $shop, ?array $intervals = null, ?array $doPreviousPeriods = null): void
    {
        if ($shop->type != ShopTypeEnum::DROPSHIPPING) {
            return;
        }

        $platformIds = Invoice::where('shop_id', $shop->id)
            ->select('platform_id')
            ->distinct()
            ->pluck('platform_id')
            ->filter();

        if ($platformIds->isEmpty()) {
            return;
        }

        foreach ($platformIds as $platformId) {
            ShopHydratePlatformSalesIntervalsSalesOrgCurrency::run($shop, $platformId, $intervals, $doPreviousPeriods);
        }
    }
}
