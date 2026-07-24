<?php

namespace App\Actions\Masters\MasterAsset\Hydrators;

use App\Actions\Catalogue\Product\UpdateProduct;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Currency;
use App\Models\Masters\MasterAsset;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterAssetHydrateMasterPricesRRPtoChild
{
    use AsAction;

    public function handle(MasterAsset $masterAsset, ?Shop $shop = null)
    {
        $currencies     = Currency::whereIn('id', $masterAsset->products->pluck('currency_id'))->get()->keyBy('id');

        $products = $masterAsset
            ->products()
            ->with(['family','shop'])
            ->when($shop, fn ($q) => $q->where('products.shop_id', $shop->id))
            ->get();

        foreach ($products as $product) {
            $shopSettings = $product->shop->settings;

            // Skip if shop setting is disabled / family not follow master prices / product not follow master prices
            if (
                !data_get($shopSettings, 'catalog.follow_master_pricing', true) ||
                $product->family?->not_follow_master_prices ||
                $product->not_follow_master_prices
            ) {
                continue;
            }

            $currency = $currencies->get($product->currency_id);

            $dataToBeUpdated = [];

            $price  = $masterAsset->getPricefromCurrency($currency);
            if ($price) {
                data_set($dataToBeUpdated, 'price', $price);
            }

            $rrpPerUnit  = $masterAsset->getRRPfromCurrency($currency);
            if ($rrpPerUnit) {
                data_set($dataToBeUpdated, 'rrp_per_unit', $rrpPerUnit);
            }

            UpdateProduct::make()->action($product, $dataToBeUpdated);
        }
    }
}
