<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\Fulfilment\StoredItem\StoreStoredItem;
use App\Actions\Fulfilment\StoredItem\UpdateStoredItem;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Portfolio\PortfolioTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Fulfilment\StoredItem\StoredItemStateEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Fulfilment\StoredItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class GetApiProductsFromShopify extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public string $commandSignature = 'shopify:import-products {shopifyUser}';

    /**
     * @throws \Throwable
     */
    public function handle(ShopifyUser $shopifyUser): void
    {
        $client = $shopifyUser->api()->getRestClient();
        $shopName = $shopifyUser->customer->shop->name;
        $shopType = $shopifyUser->customer->shop->type;
        $products = [];
        $nextPage = null;

        do {
            $response = $client->request('GET', '/admin/api/2024-01/products.json', [
                'limit' => 250,
                'page_info' => $nextPage,
                // 'vendor' => $shopName
            ]);

            if ($response['body'] == 'Not Found') {
                throw ValidationException::withMessages(['messages' => __('You dont have any products')]);
            }

            $products = array_merge($products, $response['body']['products']['container']);
            $nextPage = $response['link']['next'] ?? null;

        } while ($nextPage);

        foreach ($products as $product) {
            foreach ($product['variants'] as $variant) {
                DB::transaction(function () use ($variant, $product, $shopifyUser, $shopType) {
                    $storedItem = StoredItem::where('fulfilment_customer_id', $shopifyUser->customer->fulfilmentCustomer->id)
                        ->where('reference', $product['handle'])->first();
                    $storedItemShopify = $storedItem?->shopifyPortfolio;
                    Log::info($storedItem);
                    if ($shopType === ShopTypeEnum::FULFILMENT && !$storedItemShopify) {
                        if (!$storedItem) {
                            $storedItem = StoreStoredItem::make()->action($shopifyUser->customer->fulfilmentCustomer, [
                                'reference' => $product['handle']
                            ]);
                        }

                        $portfolio = $storedItem->portfolio;
                        if (!$portfolio) {
                            $platform = Platform::where('type', PlatformTypeEnum::SHOPIFY)->first();

                            $portfolio = StorePortfolio::make()->action($shopifyUser->customer, [
                                'stored_item_id' => $storedItem->id,
                                'type' => PortfolioTypeEnum::SHOPIFY,
                                'platform_id' => $platform->id
                            ]);
                        }

                        $shopifyUser->products()->sync([$storedItem->id => [
                            'shopify_user_id' => $shopifyUser->id,
                            'product_type' => class_basename($storedItem),
                            'product_id' => $storedItem->id,
                            'shopify_product_id' => $variant['product_id'],
                            'portfolio_id' => $portfolio->id
                        ]]);

                        UpdateStoredItem::run($storedItem, [
                            'state' => StoredItemStateEnum::SUBMITTED,
                            'total_quantity' => $variant['inventory_quantity']
                        ]);
                    }
                });
            }
        }
    }

    public function asCommand(Command $command)
    {
        $shopifyUser = ShopifyUser::find($command->argument('shopifyUser'));

        $this->handle($shopifyUser);
    }

    public function asController(ShopifyUser $shopifyUser): void
    {
        $this->handle($shopifyUser);
    }
}
