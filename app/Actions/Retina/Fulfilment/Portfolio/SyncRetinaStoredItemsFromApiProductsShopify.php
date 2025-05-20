<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Fulfilment\Portfolio;

use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\Fulfilment\StoredItem\StoreStoredItem;
use App\Actions\Fulfilment\StoredItem\UpdateStoredItem;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Fulfilment\StoredItem\StoredItemStateEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Fulfilment\StoredItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class SyncRetinaStoredItemsFromApiProductsShopify extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    public function handle(ShopifyUser $shopifyUser): void
    {
        $client = $shopifyUser->api()->getRestClient();
        $shopType = $shopifyUser->customer->shop->type;
        $products = [];
        $nextPage = null;

        do {
            $response = $client->request('GET', '/admin/api/2024-01/products.json', [
                'limit' => 250,
                'page_info' => $nextPage,
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
                    $storedItemShopify = $shopifyUser->products()->where('shopify_product_id', $variant['product_id'])->first();

                    if ($shopType === ShopTypeEnum::FULFILMENT && !$storedItemShopify) {
                        if (!$storedItem) {
                            $storedItem = StoreStoredItem::make()->action($shopifyUser->customer->fulfilmentCustomer, [
                                'reference' => $product['handle'],
                                'total_quantity' => $variant['inventory_quantity']
                            ]);
                        }

                        $portfolio = $storedItem->portfolio;
                        if (!$portfolio) {

                            $portfolio = StorePortfolio::make()->action(
                                $shopifyUser->customerSalesChannel,
                                $storedItem,
                                []
                            );
                        }

                        $shopifyUser->products()->sync([$storedItem->id => [
                            'shopify_user_id' => $shopifyUser->id,
                            'product_type' => class_basename($storedItem),
                            'product_id' => $storedItem->id,
                            'shopify_product_id' => $variant['product_id'],
                            'portfolio_id' => $portfolio->id
                        ]]);

                        UpdateStoredItem::run($storedItem, [
                            'state' => StoredItemStateEnum::ACTIVE
                        ]);
                    }
                });
            }
        }
    }

    /**
     * @throws \Throwable
     */
    public function asController(CustomerSalesChannel $customerSalesChannel): void
    {
        /** @var ShopifyUser $shopifyUser */
        $shopifyUser = $customerSalesChannel->user;

        $this->handle($shopifyUser);
    }
}
