<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Mar 2025 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\Order;

use App\Actions\Fulfilment\PalletReturn\StorePalletReturn;
use App\Actions\Fulfilment\StoredItem\StoreStoredItemsToReturn;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\Retina\Dropshipping\Client\StoreRetinaClientFromPlatformUser;
use App\Actions\Retina\Dropshipping\Client\Traits\WithGeneratedTiktokAddress;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\ChannelFulfilmentStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\TiktokUser;
use App\Models\Dropshipping\TiktokUserHasProduct;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreTiktokOrder extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;
    use WithGeneratedTiktokAddress;

    public function handle(TiktokUser $tiktokUser, array $attributes): void
    {
        $tiktokOrders = $attributes;
        $address      = Arr::get($tiktokOrders, 'recipient_address');
        data_set($address, 'email', Arr::get($tiktokOrders, 'buyer_email'));

        $orderState = match (Arr::get($tiktokOrders, 'status')) {
            'AWAITING_SHIPMENT' => OrderStateEnum::SUBMITTED->value,
            'CANCEL' => OrderStateEnum::CANCELLED->value,
            'DELIVERED', 'COMPLETED' => OrderStateEnum::FINALISED->value
        };

        /** @var CustomerClient $customerClient */
        $customerClient = $tiktokUser->customer->clients()->where('email', Arr::get($attributes, 'buyer_email'))->first();
        $address        = $this->getAddressAttributes($address);

        $customerClient = StoreRetinaClientFromPlatformUser::run($tiktokUser, $address, [
            'id' => Arr::get($tiktokOrders, 'user_id')
        ], $customerClient);

        data_set($tiktokOrders, 'customer_client_id', $customerClient->id);
        data_set($tiktokOrders, 'state', $orderState);

        if ($tiktokUser->customer->is_fulfilment) {
            $this->processFulfilment($tiktokUser, $customerClient, $attributes);
        } else {
            $this->processDropshippingShop($attributes);
        }
    }

    //todo: complete this method
    protected function processDropshippingShop($attributes)
    {
        $order = StoreOrder::make()->action();
        foreach (Arr::get($attributes, 'line_items') as $lineItem) {
            StoreTransaction::run($order, []);
        }
    }

    protected function processFulfilment(TiktokUser $tiktokUser, CustomerClient $customerClient, array $attributes)
    {
        $palletReturn = StorePalletReturn::make()->actionWithDropshipping($tiktokUser->customer->fulfilmentCustomer, [
            'type'                      => PalletReturnTypeEnum::DROPSHIPPING,
            'customer_sales_channel_id' => $tiktokUser->customer_sales_channel_id,
            'platform_id'               => $tiktokUser->platform_id,
        ]);

        $storedItems  = [];
        $allComplete  = true;
        $someComplete = false;
        foreach (Arr::get($attributes, 'line_items') as $lineItem) {
            $tiktokUserHasProduct = TiktokUserHasProduct::where('tiktok_user_id', $tiktokUser->id)
                ->where('tiktok_product_id', $lineItem['product_id'])
                ->first();

            if (!$tiktokUserHasProduct) {
                continue;
            }

            $storedItems[$tiktokUserHasProduct->portfolio->item_id] = [
                'quantity' => Arr::get($lineItem, 'quantity', 1)
            ];

            $itemQuantity     = (int)$tiktokUserHasProduct->portfolio->item->total_quantity;
            $requiredQuantity = Arr::get($lineItem, 'quantity', 1);

            if ($itemQuantity >= $requiredQuantity) {
                $someComplete = true;
            } else {
                $allComplete = false;
            }
        }

        if (blank($storedItems)) {
            return false;
        }


        if ($allComplete) {
            $status = ChannelFulfilmentStateEnum::OPEN;
        } elseif ($someComplete) {
            $status = ChannelFulfilmentStateEnum::HOLD;
        } else {
            $status = ChannelFulfilmentStateEnum::INCOMPLETE;
        }

        StoreStoredItemsToReturn::make()->action($palletReturn, [
            'stored_items' => $storedItems
        ]);

        $tiktokUser->orders()->create([
            'orderable_type'     => $palletReturn->getMorphClass(),
            'orderable_id'       => $palletReturn->id,
            'state'              => $status,
            'tiktok_order_id'    => Arr::get($attributes, 'id'),
            'customer_client_id' => $customerClient->id
        ]);
    }

}
