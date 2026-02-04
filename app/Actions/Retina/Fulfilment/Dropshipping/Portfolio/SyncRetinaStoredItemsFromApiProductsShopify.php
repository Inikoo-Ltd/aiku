<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Fulfilment\Dropshipping\Portfolio;

use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\Fulfilment\StoredItem\StoreStoredItem;
use App\Actions\Fulfilment\StoredItem\UpdateStoredItem;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Fulfilment\StoredItem\StoredItemStateEnum;
use App\Events\FetchProductFromShopifyProgressEvent;
use App\Events\UploadProductToShopifyProgressEvent;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Fulfilment\StoredItem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
        $client = $shopifyUser->getShopifyClient();
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

        DB::transaction(function () use ($products, $shopifyUser, $shopType) {
            $numberSuccess = 0;
            $numberFails = 0;

            $numberTotal = array_sum(array_map(fn($product) => count($product['variants']), $products));

            foreach ($products as $product) {
                foreach ($product['variants'] as $variant) {
                    try {
                        $sku = $variant['sku'];
                        if (!$variant['sku']) {
                            $sku = Str::slug($variant['title']);
                        }

                        $storedItem = StoredItem::where('fulfilment_customer_id', $shopifyUser->customer->fulfilmentCustomer->id)
                            ->where('reference', $sku)->first();
                        $storedItemShopify = $shopifyUser->customerSalesChannel->portfolios()->where('platform_product_id', Arr::get($product, 'variants.0.product_id'))->first();

                        $qty = Arr::get($variant, 'inventory_quantity');
                        if ($shopType === ShopTypeEnum::FULFILMENT && !$storedItemShopify) {
                            if (!$storedItem) {

                                if ($qty == 0 || $qty < 0) {
                                    continue;
                                }

                                $storedItem = StoreStoredItem::make()->action($shopifyUser->customer->fulfilmentCustomer, [
                                    'reference' => $sku,
                                    'total_quantity' => $qty
                                ]);
                            }

                            $portfolio = $storedItem->portfolio;
                            if (!$portfolio) {

                                StorePortfolio::make()->action(
                                    $shopifyUser->customerSalesChannel,
                                    $storedItem,
                                    [
                                        'platform_product_id' => Arr::get($product, 'admin_graphql_api_id'),
                                        'platform_product_variant_id' => Arr::get($variant, 'admin_graphql_api_id'),
                                    ]
                                );
                            }

                            UpdateStoredItem::run($storedItem, [
                                'state' => StoredItemStateEnum::ACTIVE
                            ]);
                        }
                        $numberSuccess++;
                    } catch (ValidationException $exception) {
                        $numberFails++;
                    }
                    Log::info('o:' . $numberSuccess);
                    FetchProductFromShopifyProgressEvent::dispatch($shopifyUser, [
                        'number_total' => $numberTotal,
                        'number_success' => $numberSuccess,
                        'number_fails' => $numberFails
                    ]);
                    Log::info('w:' . $numberSuccess);
                }
            }

            /*broadcast(new FetchProductFromShopifyProgressEvent($shopifyUser, [
                'number_total' => $numberTotal,
                'number_success' => $numberTotal,
                'number_fails' => $numberFails
            ]));*/
        });
    }

    /**
     * @throws \Throwable
     */
    public function asController(CustomerSalesChannel $customerSalesChannel): void
    {
        /** @var ShopifyUser $shopifyUser */
        $shopifyUser = $customerSalesChannel->user;

        SyncRetinaStoredItemsFromApiProductsShopify::run($shopifyUser);
    }
}
