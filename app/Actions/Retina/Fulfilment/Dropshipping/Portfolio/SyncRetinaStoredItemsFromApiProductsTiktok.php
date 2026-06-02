<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Fulfilment\Dropshipping\Portfolio;

use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\Fulfilment\StoredItem\StoreStoredItem;
use App\Actions\Fulfilment\StoredItem\UpdateStoredItem;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Fulfilment\StoredItem\StoredItemStateEnum;
use App\Events\FetchProductFromPlatformProgressEvent;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\TiktokUser;
use App\Models\Fulfilment\StoredItem;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class SyncRetinaStoredItemsFromApiProductsTiktok extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    public function handle(TiktokUser $tiktokUser): void
    {
        $tiktokProducts = [];
        $pageToken = [];
        $productCount = 0;

        do {
            $products = $tiktokUser->getProducts([
                'status' => 'ACTIVATE'
            ], [
                'page_size' => 100,
                ...$pageToken
            ]);

            $tiktokProducts = array_merge($tiktokProducts, Arr::get($products, 'data.products', []));
            $pageToken = ['page_token' => Arr::get($products, 'data.next_page_token')];

            $totalCount = Arr::get($products, 'data.total_count');
            $productCount = $productCount + count(Arr::get($products, 'data.products', []));
        } while ($totalCount > 0 && $totalCount > $productCount);

        $shopType = $tiktokUser->customer->shop->type;

        DB::transaction(function () use ($tiktokProducts, $productCount, $tiktokUser, $shopType) {
            $numberSuccess = 0;
            $numberFails = 0;

            $numberTotal = $productCount;
            foreach ($tiktokProducts as $product) {
                try {
                    $title = Arr::get($product, 'title');
                    $reference = Arr::get($product, 'skus.0.seller_sku');

                    if (!$reference) {
                        $reference = Str::slug($title);
                    }

                    $storedItem = StoredItem::where('fulfilment_customer_id', $tiktokUser->customer->fulfilmentCustomer->id)
                        ->where('reference', $reference)
                        ->first();

                    if ($shopType === ShopTypeEnum::FULFILMENT) {
                        if (!$storedItem) {
                            $storedItem = StoreStoredItem::make()->action($tiktokUser->customer->fulfilmentCustomer, [
                                'reference' => $reference,
                                'name' => $title
                            ]);
                        }

                        $portfolio = $storedItem->portfolio;
                        if (!$portfolio) {
                            $portfolio = StorePortfolio::make()->action(
                                $tiktokUser->customerSalesChannel,
                                $storedItem,
                                [
                                    'platform_product_id' => Arr::get($product, 'id'),
                                    'platform_product_variant_id' => Arr::get($product, 'id')
                                ]
                            );
                        }

                        UpdatePortfolio::run($portfolio, [
                            'item_id' => $storedItem->id,
                            'item_type' => class_basename(StoredItem::class),
                            'item_code' => $storedItem->reference,
                            'item_name' => $title,
                            'customer_product_name' => $title,
                            'customer_description' => Arr::get($product, 'description'),
                            'platform_product_id' => Arr::get($product, 'id'),
                            'platform_product_variant_id' => Arr::get($product, 'id'),
                        ]);

                        UpdateStoredItem::run($storedItem, [
                            'state' => StoredItemStateEnum::ACTIVE,
                            'total_quantity' => Arr::get($product, 'skus.0.inventory.0.quantity', 0)
                        ]);
                    }
                    $numberSuccess++;
                } catch (ValidationException $exception) {
                    $numberFails++;
                }

                FetchProductFromPlatformProgressEvent::dispatch($tiktokUser, [
                    'number_total' => $numberTotal,
                    'number_success' => $numberSuccess,
                    'number_fails' => $numberFails
                ]);
            }

            FetchProductFromPlatformProgressEvent::dispatch($tiktokUser, [
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
        /** @var TiktokUser $tiktokUser */
        $tiktokUser = $customerSalesChannel->user;

        SyncRetinaStoredItemsFromApiProductsTiktok::dispatch($tiktokUser);
    }

    public string $commandSignature = 'SyncRetinaStoredItemsFromApiProductsTiktok {customer_sales_channel}';

    public function asCommand(Command $command)
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customer_sales_channel'))->firstOrFail();

        $this->handle($customerSalesChannel->user);
    }
}
