<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 21-11-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Catalogue\Shop;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateTopSellers
{
    use AsAction;
    use WithEnumStats;

    private Shop $shop;

    public function __construct(Shop $shop)
    {
        $this->shop = $shop;
    }

    public function getJobMiddleware(): array
    {
        return [(new WithoutOverlapping($this->shop->id))->dontRelease()];
    }

    public function handle(Shop $shop): void
    {
        $timesUpdate = ['1d', '1w', '1m', '1y', 'all'];
        foreach ($timesUpdate as $timeUpdate) {
            $topFamily = $shop->getFamilies()->sortByDesc(function ($family) use ($timeUpdate) {
                return $family->salesIntervals->{'sales_'.$timeUpdate};
            })->first();

            $topDepartment = $shop->departments()->sortByDesc(function ($department) use ($timeUpdate) {
                return $department->salesIntervals->{'sales_'.$timeUpdate};
            })->first();

            $topProduct = $shop->products()
                ->select('products.asset_id', 'products.id')
                ->leftJoin('assets', 'products.asset_id', '=', 'assets.id')
                ->leftJoin('asset_sales_intervals', 'assets.id', '=', 'asset_sales_intervals.asset_id')
                ->orderByDesc("asset_sales_intervals.sales_{$timeUpdate}")
                ->first();

            $dataToUpdate = [];
            if ($topFamily && $topFamily->stats->{'shop_amount_'.$timeUpdate} > 0) {
                data_set($dataToUpdate, "top_{$timeUpdate}_family_id", $topFamily->id);
            }
            if ($topDepartment && $topDepartment->stats->{'shop_amount_'.$timeUpdate} > 0) {
                data_set($dataToUpdate, "top_{$timeUpdate}_department_id", $topDepartment->id);
            }
            if ($topProduct && ($topProduct->asset->salesIntervals->{'sales_'.$timeUpdate} ?? 0) > 0) {
                data_set($dataToUpdate, "top_{$timeUpdate}_product_id", $topProduct->id);
            }

            $shop->stats->update($dataToUpdate);
        }
    }

    public string $commandSignature = 'hydrate:top_sellers';

    public function asCommand(): void
    {
        $shops = SHOP::all();
        foreach ($shops as $shop) {
            $this->handle($shop);
        }
    }
}
