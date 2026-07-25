<?php

namespace App\Actions\Masters\MasterAsset\Hydrators;

use App\Actions\Catalogue\Product\UpdateProduct;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Currency;
use App\Models\Masters\MasterAsset;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterAssetHydrateMasterPricesRRPtoChild
{
    use AsAction;

    public function handle(MasterAsset $masterAsset, ?Shop $shop = null, bool $skipBreakWebpageCache = false): void
    {
        $currencies = Currency::whereIn('id', $masterAsset->products->pluck('currency_id'))->get()->keyBy('id');

        $products = $masterAsset
            ->products()
            ->with(['family', 'shop'])
            ->when($shop, fn ($q) => $q->where('products.shop_id', $shop->id))
            ->get();

        /** @var Product $product */
        foreach ($products as $product) {
            $shopSettings = $product->shop->settings;

            // Skip if shop setting is disabled / family not follow master prices / product not follow master prices
            if (
                !data_get($shopSettings, 'catalog.follow_master_pricing', true)
                || $product->family?->not_follow_master_prices
                || $product->not_follow_master_prices
            ) {
                continue;
            }

            /** @var Currency $currency */
            $currency = $currencies->get($product->currency_id);

            $dataToBeUpdated = [];

            $price = $masterAsset->getPriceFromCurrency($currency);
            if ($price) {
                data_set($dataToBeUpdated, 'price', $price);
            }

            $rrpPerUnit = $masterAsset->getRrpFromCurrency($currency);
            if ($rrpPerUnit) {
                data_set($dataToBeUpdated, 'rrp_per_unit', $rrpPerUnit);
            }

            $updateProduct = UpdateProduct::make();
            $updateProduct->skipBreakWebpageCache = $skipBreakWebpageCache;
            $updateProduct->action($product, $dataToBeUpdated);
        }
    }
}
