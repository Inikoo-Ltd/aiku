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
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Fulfilment\StoredItem\StoredItemStateEnum;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Fulfilment\StoredItem;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
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
            DB::transaction(function () use ($product, $shopifyUser, $shopType) {
                $storedItem = StoredItem::where('fulfilment_customer_id', $shopifyUser->customer->fulfilmentCustomer->id)
                    ->where('reference', $product['handle'])->first();
                $storedItemShopify = $shopifyUser->customerSalesChannel->portfolios()->where('platform_product_id', Arr::get($product, 'variants.0.product_id'))->first();

                if ($shopType === ShopTypeEnum::FULFILMENT && !$storedItemShopify) {
                    if (!$storedItem) {
                        $storedItem = StoreStoredItem::make()->action($shopifyUser->customer->fulfilmentCustomer, [
                            'reference' => $product['handle'],
                            'total_quantity' => Arr::get($product, 'variants.0.inventory_quantity')
                        ]);
                    }

                    $portfolio = $storedItem->portfolio;
                    if (!$portfolio) {

                        StorePortfolio::make()->action(
                            $shopifyUser->customerSalesChannel,
                            $storedItem,
                            []
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
    public function asCommand(Command $command): void
    {
        $shopifyUser = ShopifyUser::find($command->argument('shopifyUser'));

        $this->handle($shopifyUser);
    }

    /**
     * @throws \Throwable
     */
    public function asController(ShopifyUser $shopifyUser): void
    {
        $this->handle($shopifyUser);
    }
}
