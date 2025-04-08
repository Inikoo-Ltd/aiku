<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\Product;

use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\Fulfilment\StoredItem\StoreStoredItem;
use App\Actions\Fulfilment\StoredItem\UpdateStoredItem;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Portfolio\PortfolioTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Fulfilment\StoredItem\StoredItemStateEnum;
use App\Models\Dropshipping\TiktokUser;
use App\Models\Fulfilment\StoredItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class GetProductsFromTiktokApi extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public string $commandSignature = 'tiktok:import-products {tiktokUser}';

    /**
     * @throws \Throwable
     */
    public function handle(TiktokUser $tiktokUser): void
    {
        $client = $tiktokUser->api()->getRestClient();
        $shopName = $tiktokUser->customer->shop->name;
        $shopType = $tiktokUser->customer->shop->type;
        $products = [];
        $nextPage = null;

        do {
            $response = $client->request('GET', '/admin/api/2024-01/products.json', [
                'limit' => 250,
                'page_info' => $nextPage,
                'vendor' => $shopName
            ]);

            if ($response['body'] == 'Not Found') {
                throw ValidationException::withMessages(['messages' => __('You dont have any products')]);
            }

            $products = array_merge($products, $response['body']['products']['container']);
            $nextPage = $response['link']['next'] ?? null;

        } while ($nextPage);

        foreach ($products as $product) {
            foreach ($product['variants'] as $variant) {
                DB::transaction(function () use ($variant, $product, $tiktokUser, $shopType) {
                    $storedItem = StoredItem::where('fulfilment_customer_id', $tiktokUser->customer->fulfilmentCustomer->id)
                        ->where('reference', $product['handle'])->first();
                    $storedItemShopify = $storedItem?->shopifyPortfolio;

                    if ($shopType === ShopTypeEnum::FULFILMENT && !$storedItemShopify) {
                        if (!$storedItem) {
                            $storedItem = StoreStoredItem::make()->action($tiktokUser->customer->fulfilmentCustomer, [
                                'reference' => $product['handle']
                            ]);
                        }

                        $portfolio = $storedItem->portfolio;
                        if (!$portfolio) {
                            $portfolio = StorePortfolio::make()->action($tiktokUser->customer, [
                                'stored_item_id' => $storedItem->id,
                                'type' => PortfolioTypeEnum::SHOPIFY
                            ]);
                        }

                        $tiktokUser->products()->sync([$storedItem->id => [
                            'shopify_user_id' => $tiktokUser->id,
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
        $tiktokUser = TiktokUser::find($command->argument('TiktokUser'));

        $this->handle($tiktokUser);
    }

    public function asController(TiktokUser $tiktokUser): void
    {
        $this->handle($tiktokUser);
    }
}
