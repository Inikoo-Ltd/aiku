<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Mar 2025 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\Order;

use App\Actions\Dropshipping\CustomerClient\StoreCustomerClient;
use App\Actions\Fulfilment\PalletReturn\StorePalletReturn;
use App\Actions\Fulfilment\StoredItem\StoreStoredItemsToReturn;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\ShopifyFulfilmentReasonEnum;
use App\Enums\Dropshipping\ChannelFulfilmentStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\TiktokUser;
use App\Models\TiktokUserHasProduct;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreTiktokOrder extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(TiktokUser $tiktokUser, array $attributes)
    {
        $tiktokOrders = [];
        $orderState = match (Arr::get($attributes, 'status')) {
            'AWAITING_SHIPMENT' => OrderStateEnum::SUBMITTED->value,
            'CANCEL' => OrderStateEnum::CANCELLED->value,
            'DELIVERED', 'COMPLETED' => OrderStateEnum::FINALISED->value
        };

        /** @var CustomerClient $customerClient */
        $customerClient = $tiktokUser->customer->clients()->where('email', Arr::get($attributes, 'buyer_email'))->first();

        if (!$customerClient) {
            $attributes = $this->getAttributes($tiktokUser->customer, Arr::get($tiktokOrders, 'recipient_address'));

            $customerClient = StoreCustomerClient::make()->action($customerClient->customer, $attributes);
        }

        data_set($tiktokOrders, 'customer_client_id', $customerClient->id);
        data_set($tiktokOrders, 'state', $orderState);

        if ($tiktokUser->customer->is_fulfilment) {
            $palletReturn = StorePalletReturn::make()->actionWithDropshipping($tiktokUser->customer->fulfilmentCustomer, [
                'type' => PalletReturnTypeEnum::DROPSHIPPING,
                'platform_id' => $tiktokUser->customer->platforms()->where('type', PlatformTypeEnum::TIKTOK->value)->first()->id
            ]);

            $storedItems = [];
            $allComplete = true;
            $someComplete = false;
            foreach (Arr::get($attributes, 'line_items') as $lineItem) {
                $tiktokUserHasProduct = TiktokUserHasProduct::where('tiktok_user_id', $tiktokUser->id)
                    ->where('tiktok_product_id', $lineItem['product_id'])
                    ->first();

                if (!$tiktokUserHasProduct) {
                    continue;
                }

                $storedItems[$tiktokUserHasProduct->portfolio->item_id] = [
                    'quantity' => $lineItem['quantity']
                ];

                $itemQuantity = (int) $tiktokUserHasProduct->portfolio->item->total_quantity;
                $requiredQuantity = $lineItem['quantity'];

                if ($itemQuantity >= $requiredQuantity) {
                    $someComplete = true;
                } else {
                    $allComplete = false;
                }
            }

            if (blank($storedItems)) {
                return false;
            }

            $reasons = [
                'no_fulfilment_reason' => ShopifyFulfilmentReasonEnum::INVENTORY_OUT_OF_STOCK,
                'no_fulfilment_reason_notes' => ShopifyFulfilmentReasonEnum::INVENTORY_OUT_OF_STOCK->notes()[ShopifyFulfilmentReasonEnum::INVENTORY_OUT_OF_STOCK->value],
            ];

            if ($allComplete) {
                $status = ChannelFulfilmentStateEnum::OPEN;
                $reasons = [];
            } elseif ($someComplete) {
                $status = ChannelFulfilmentStateEnum::HOLD;
            } else {
                $status = ChannelFulfilmentStateEnum::INCOMPLETE;
            }

            StoreStoredItemsToReturn::make()->action($palletReturn, [
                'stored_items' => $storedItems
            ]);

            data_set($tiktokOrders, 'state', $status);
        } else {
            $order = StoreOrder::make()->action();
            foreach (Arr::get($attributes, 'line_items') as $lineItem) {
                StoreTransaction::run($order, []);
            }
        }

        return $tiktokUser->orders()->create($attributes);
    }
}
