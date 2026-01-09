<?php

namespace App\Actions\Catalogue\Shop\External\Shopify;

use App\Actions\Catalogue\HistoricAsset\StoreHistoricAsset;
use App\Actions\Catalogue\Product\StoreProduct;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Enums\Catalogue\Product\ProductTradeConfigEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Support\Arr;

class GetShopifyProducts extends OrgAction
{
    public string $commandSignature = 'external_shop:shopify_products {shop}';

    public function handle(Shop $shop): void
    {
        $shopifyUser = ShopifyUser::where('external_shop_id', $shop->id)->first();
        $products = $shopifyUser->getShopifyProducts(['first' => 250]);

        foreach (Arr::get($products, 'data.products.edges', []) as $product) {
            $product = Arr::get($product, 'node');

            foreach (Arr::get($product, 'variants.edges', []) as $variant) {
                $variant = Arr::get($variant, 'node');

                if(! Arr::get($variant, 'sku')) {
                    return;
                }

                if (Product::where('shop_id', $shop->id)->where('code', $variant['sku'])->exists()) {
                    continue;
                }

                $product = StoreProduct::make()->action($shop, [
                    'code' => $variant['sku'],
                    'name' => $product['title'],
                    'description' => $product['title'],
                    'rrp' => $variant['price'],
                    'price' => $variant['price'],
                    'unit' => 'Piece',
                    'units' => 1,
                    'is_main' => true,
                    'trade_config' => ProductTradeConfigEnum::AUTO,
                    'status' => ProductStatusEnum::FOR_SALE,
                    'state' => ProductStateEnum::ACTIVE,
                    'data' => [
                        'shopify' => $variant
                    ]
                ], strict: false);

                StoreHistoricAsset::run($product, []);

                echo $product->code . "\n";
            }
        }
    }

    public function asCommand($command): int
    {
        $this->handle(Shop::where('slug', $command->argument('shop'))->first());

        return 0;
    }
}
