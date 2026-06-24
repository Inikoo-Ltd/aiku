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
use App\Events\FetchProductFromPlatformProgressEvent;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\WooCommerceUser;
use App\Models\Fulfilment\StoredItem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class SyncRetinaStoredItemsFromApiProductsWooCommerce extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    public function handle(WooCommerceUser $wooCommerceUser): void
    {
        $shopType = $wooCommerceUser->customer->shop->type;
        $products = [];
        $page = 1;

        do {
            $response = $wooCommerceUser->getWooCommerceProducts([
                'page' => $page
            ]);

            if (!empty($response)) {
                $products = array_merge($products, $response);
            }

            if (count($response) === 10) {
                $nextPage = true;
                $page++;
            } else {
                $nextPage = false;
            }

        } while ($nextPage);

        DB::transaction(function () use ($products, $wooCommerceUser, $shopType) {
            $numberSuccess = 0;
            $numberFails = 0;
            $numberTotal = count($products);

            foreach ($products as $product) {
                try {
                    $name = Arr::get($product, 'name');
                    $reference = Arr::get($product, 'slug');

                    if (!$reference) {
                        $reference = Str::slug($name);
                    }

                    $storedItem = StoredItem::where('fulfilment_customer_id', $wooCommerceUser->customer->fulfilmentCustomer->id)
                        ->where('reference', $reference)->first();
                    $storedItemShopify = $wooCommerceUser->customerSalesChannel->portfolios()->where('platform_product_id', Arr::get($product, 'id'))->first();

                    if ($shopType === ShopTypeEnum::FULFILMENT && !$storedItemShopify) {
                        if (!$storedItem) {
                            $storedItem = StoreStoredItem::make()->action($wooCommerceUser->customer->fulfilmentCustomer, [
                                'reference' => $reference,
                                'name' => $name,
                                'total_quantity' => Arr::get($product, 'stock_quantity')
                            ]);
                        }

                        $portfolio = $storedItem->portfolio;
                        if (!$portfolio) {

                            StorePortfolio::make()->action(
                                $wooCommerceUser->customerSalesChannel,
                                $storedItem,
                                [
                                    'platform_product_id' => (string)Arr::get($product, 'id'),
                                ]
                            );
                        }

                        UpdateStoredItem::run($storedItem, [
                            'state' => StoredItemStateEnum::ACTIVE
                        ]);
                    }

                    $numberSuccess++;
                } catch (\Throwable $th) {
                    $numberFails++;
                }

                FetchProductFromPlatformProgressEvent::dispatch($wooCommerceUser, [
                    'number_total' => $numberTotal,
                    'number_success' => $numberSuccess,
                    'number_fails' => $numberFails
                ]);
            }

            FetchProductFromPlatformProgressEvent::dispatch($wooCommerceUser, [
                'number_total' => $numberTotal,
                'number_success' => $numberTotal,
                'number_fails' => $numberFails
            ]);
        });
    }

    /**
     * @throws \Throwable
     */
    public function asController(CustomerSalesChannel $customerSalesChannel): void
    {
        /** @var WooCommerceUser $wooCommerce */
        $wooCommerce = $customerSalesChannel->user;

        SyncRetinaStoredItemsFromApiProductsWooCommerce::dispatch($wooCommerce);
    }
}
