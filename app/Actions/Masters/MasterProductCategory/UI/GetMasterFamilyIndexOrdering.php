<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 18:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Actions\Masters\MasterAsset\UI\IndexMasterProducts;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\UI\SupplyChain\MasterFamilyTabsEnum;
use App\Http\Resources\Masters\MasterProductsResource;
use App\Models\Catalogue\Shop;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The family's products in their saved order, each with what is needed to decide the order for
 * every shop at once: group sales of the last 12 months and how many open shops sell it and have
 * it in stock.
 */
class GetMasterFamilyIndexOrdering
{
    use AsObject;

    /**
     * @return array{products: array<int, array<string, mixed>>, open_shops_count: int, currency_code: string}
     */
    public function handle(MasterProductCategory $masterFamily): array
    {
        $masterAssets = IndexMasterProducts::run($masterFamily, MasterFamilyTabsEnum::INDEX_ORDERING->value, null, true);

        $salesLast12Months = DB::table('master_asset_time_series_records')
            ->join('master_asset_time_series', 'master_asset_time_series.id', '=', 'master_asset_time_series_records.master_asset_time_series_id')
            ->where('master_asset_time_series.frequency', TimeSeriesFrequencyEnum::DAILY->value)
            ->whereIn('master_asset_time_series.master_asset_id', $masterAssets->pluck('id'))
            ->where('master_asset_time_series_records.from', '>=', now()->subYear()->startOfDay())
            ->groupBy('master_asset_time_series.master_asset_id')
            ->selectRaw('master_asset_time_series.master_asset_id, sum(master_asset_time_series_records.sales_grp_currency_external) as sales, sum(master_asset_time_series_records.sold) as sold')
            ->get()
            ->keyBy('master_asset_id');

        $products = MasterProductsResource::collection($masterAssets)->resolve();
        foreach ($masterAssets->values() as $position => $masterAsset) {
            $openShopProducts = $masterAsset->products->filter(fn ($product) => $product->shop?->state == ShopStateEnum::OPEN);

            $products[$position]['sales_12m']      = round((float) ($salesLast12Months->get($masterAsset->id)?->sales ?? 0), 2);
            $products[$position]['sold_12m']       = (float) ($salesLast12Months->get($masterAsset->id)?->sold ?? 0);
            $products[$position]['shops_selling']  = $openShopProducts->whereIn('state', [ProductStateEnum::ACTIVE, ProductStateEnum::DISCONTINUING])->count();
            $products[$position]['shops_in_stock'] = $openShopProducts->filter(fn ($product) => $product->available_quantity > 0)->count();
        }

        return [
            'products'         => $products,
            'open_shops_count' => Shop::where('master_shop_id', $masterFamily->master_shop_id)->where('state', ShopStateEnum::OPEN)->count(),
            'currency_code'    => $masterFamily->group->currency->code,
        ];
    }
}
