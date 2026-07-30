<?php

namespace App\Actions\Masters\MasterAsset\Hydrators;

use App\Actions\Catalogue\Product\UpdateProduct;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Currency;
use App\Models\Masters\MasterAsset;
use Closure;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterAssetHydrateMasterPricesRRPtoChild
{
    use AsAction;

    /** @param Closure(Product, int, int): void|null $afterEachProduct called with (product, done, total) */
    public function handle(MasterAsset $masterAsset, ?Shop $shop = null, bool $bulkPriceUpdate = false, bool $skipWebpageCacheBreak = false, ?Closure $afterEachProduct = null): void
    {
        $currencies = Currency::whereIn('id', $masterAsset->products->pluck('currency_id'))->get()->keyBy('id');

        $products = $masterAsset
            ->products()
            ->with(['family', 'shop'])
            ->when($shop, fn ($q) => $q->where('products.shop_id', $shop->id))
            ->get()
            ->filter(function (Product $product) {
                // Skip if shop setting is disabled / family not follow master prices / product not follow master prices
                return data_get($product->shop->settings, 'catalog.follow_master_pricing', true)
                    && !$product->family?->not_follow_master_prices
                    && !$product->not_follow_master_prices;
            })
            ->values();

        $total = $products->count();

        /** @var Product $product */
        foreach ($products as $index => $product) {
            /** @var Currency $currency */
            $currency = $currencies->get($product->currency_id);

            $dataToBeUpdated = [];

            $price = $masterAsset->getPriceFromCurrency($currency);
            if ($price) {
                data_set($dataToBeUpdated, 'price', $price);
            }

            $rrp = $masterAsset->getRrpFromCurrency($currency);
            if ($rrp) {
                data_set($dataToBeUpdated, 'rrp', $rrp);
            }

            $updateProduct = UpdateProduct::make();
            $updateProduct->bulkPriceUpdate = $bulkPriceUpdate;
            $updateProduct->skipWebpageCacheBreak = $skipWebpageCacheBreak;
            $updateProduct->action($product, $dataToBeUpdated);

            if ($afterEachProduct) {
                $afterEachProduct($product, $index + 1, $total);
            }
        }
    }
}
