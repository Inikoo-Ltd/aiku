<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Mar 2025 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\Fulfilment;

use App\Actions\Dropshipping\Tiktok\Order\StoreTiktokOrder;
use App\Actions\Fulfilment\PalletReturn\StorePalletReturn;
use App\Actions\Fulfilment\PalletReturn\SubmitPalletReturn;
use App\Actions\Fulfilment\StoredItem\StoreStoredItemsToReturn;
use App\Actions\Retina\Dropshipping\Client\Traits\WithGeneratedTiktokAddress;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\TiktokUser;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\StoredItem;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreTiktokFulfilmentOrder extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;
    use WithGeneratedTiktokAddress;

    /**
     * @throws \Throwable
     */
    public function handle(TiktokUser $tiktokUser, array $tiktokOrders): void
    {
        $tiktokOrderClass = new StoreTiktokOrder();
        $fulfilmentCustomer = $tiktokUser->customer->fulfilmentCustomer;

        $customerClient = $tiktokOrderClass->digestTiktokCustomerClient($tiktokUser, $tiktokOrders);
        $orderedProducts = collect(Arr::get($tiktokOrders, 'line_items', []));

        $tiktokUserHasProductExists = $tiktokUser->customerSalesChannel->portfolios()
            ->whereIn('platform_product_id', $orderedProducts->pluck('product_id'))->exists();

        $existOrder = PalletReturn::where('platform_order_id', Arr::get($tiktokOrders, 'id'))->first();

        if ($existOrder) {
            return;
        }

        if ($tiktokUserHasProductExists) {
            $palletReturn = StorePalletReturn::make()->actionWithDropshipping($fulfilmentCustomer, [
                'platform_id'               => $tiktokUser->platform_id,
                'customer_sales_channel_id' => $tiktokUser->customer_sales_channel_id,
                'date'                      => now(),
                'delivery_address'          => $tiktokOrderClass->digestTiktokAddress($tiktokOrders),
                'data'                      => ['tiktok_data' => $tiktokOrders],
                'platform_order_id'         => Arr::get($tiktokOrders, 'id'),
                'is_collection'             => false
            ]);

            $storedItemModels = [];
            foreach ($orderedProducts as $tiktokProduct) {
                /** @var Portfolio $portfolio */
                $portfolio = $tiktokUser->customerSalesChannel->portfolios()
                    ->where('platform_product_id', $tiktokProduct['product_id'])->first();

                if ($portfolio) {
                    /** @var StoredItem $product */
                    $storedItem = $portfolio->item;
                    if (!$storedItem) {
                        \Sentry\captureMessage('Portfolio '.$portfolio->id.' does not have a product');
                        continue;
                    }

                    $storedItemModels[$storedItem->id] = [
                        'quantity' => $tiktokProduct['quantity']
                    ];
                }
            }

            StoreStoredItemsToReturn::make()->action(
                palletReturn: $palletReturn,
                modelData: [
                    'stored_items' => $storedItemModels
                ]
            );

            SubmitPalletReturn::run($palletReturn, []);

            $palletReturn->refresh();
        }
    }
}
