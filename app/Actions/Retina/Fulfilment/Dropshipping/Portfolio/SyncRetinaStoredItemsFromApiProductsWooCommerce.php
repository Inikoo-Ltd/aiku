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
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\WooCommerceUser;
use App\Models\Fulfilment\StoredItem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
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
        $nextPage = null;

        do {
            $response = $wooCommerceUser->getWooCommerceProducts();

            $products = $response;
        } while ($nextPage);

        foreach ($products as $product) {
            DB::transaction(function () use ($product, $wooCommerceUser, $shopType) {
                $storedItem = StoredItem::where('fulfilment_customer_id', $wooCommerceUser->customer->fulfilmentCustomer->id)
                    ->where('reference', $product['slug'])->first();
                $storedItemShopify = $wooCommerceUser->customerSalesChannel->portfolios()->where('platform_product_id', Arr::get($product, 'id'))->first();

                if ($shopType === ShopTypeEnum::FULFILMENT && !$storedItemShopify) {
                    if (!$storedItem) {
                        $storedItem = StoreStoredItem::make()->action($wooCommerceUser->customer->fulfilmentCustomer, [
                            'reference' => $product['slug'],
                            'name' => $product['name'],
                            'total_quantity' => Arr::get($product, 'stock_quantity')
                        ]);
                    }

                    $portfolio = $storedItem->portfolio;
                    if (!$portfolio) {

                        StorePortfolio::make()->action(
                            $wooCommerceUser->customerSalesChannel,
                            $storedItem,
                            [
                                'platform_product_id' => Arr::get($product, 'id'),
                            ]
                        );
                    }

                    UpdateStoredItem::run($storedItem, [
                        'state' => StoredItemStateEnum::ACTIVE
                    ]);
                }
            });

        }
    }

    /**
     * @throws \Throwable
     */
    public function asController(CustomerSalesChannel $customerSalesChannel): void
    {
        /** @var WooCommerceUser $wooCommerce */
        $wooCommerce = $customerSalesChannel->user;

        $this->handle($wooCommerce);
    }
}
