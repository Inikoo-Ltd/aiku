<?php

namespace App\Actions\Catalogue\Shop\External\Shopify;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\ActionRequest;

class CallbackShopifyExternalShopFetchStock extends OrgAction
{
    public const int CACHE_MINUTES = 5;

    /**
     * Shopify asks a fulfilment service for its stock by SKU; it gets the same quantities the stock push sends.
     *
     * @return array<string, int>
     */
    public function handle(Shop $shop): array
    {
        return Cache::remember('shopify-external-shop:fetch-stock:'.$shop->id, now()->addMinutes(self::CACHE_MINUTES), function () use ($shop) {
            $inventory = UpdateShopifyProductInventoryQuantity::make();
            $stock     = [];

            Product::where('shop_id', $shop->id)
                ->whereNotNull('marketplace_id')
                ->where('state', '!=', ProductStateEnum::IN_PROCESS)
                ->select(['id', 'code', 'state', 'available_quantity'])
                ->chunkById(1000, function ($products) use (&$stock, $inventory) {
                    foreach ($products as $product) {
                        $stock[$product->code] = $inventory->getQuantityToPush($product);
                    }
                });

            return $stock;
        });
    }

    /**
     * @return array<string, int>
     */
    public function asController(ShopifyUser $shopifyUser, ActionRequest $request): array
    {
        $shop = $shopifyUser->externalShop;

        if ($shopifyUser->trashed() || !$shop || $shop->type !== ShopTypeEnum::EXTERNAL || $shop->engine !== ShopEngineEnum::SHOPIFY) {
            return [];
        }

        $this->initialisationFromShop($shop, $request);

        $stock = $this->handle($shop);

        if ($sku = $request->query('sku')) {
            return array_key_exists($sku, $stock) ? [$sku => $stock[$sku]] : [];
        }

        return $stock;
    }
}
