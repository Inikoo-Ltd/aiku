<?php

/*
 * author Arya Permana - Kirin
 * created on 12-06-2025-09h-28m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Amazon\Orders;

use App\Actions\OrgAction;
use App\Actions\Retina\Dropshipping\Client\Traits\WithGeneratedMagentoAddress;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dropshipping\AmazonUser;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class FulfillOrderToAmazon extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;
    use WithGeneratedMagentoAddress;

    /**
     * @throws \Throwable
     */
    public function handle(Order $order): void
    {
        $fulfillOrderId = Arr::get($order->data, 'entity_id');

        /** @var AmazonUser $amazonUser */
        $amazonUser = $order->customerSalesChannel->user;

        /** @var DeliveryNote $deliveryNote */
        $deliveryNote = $order->deliveryNotes->first();

        $shipments = $deliveryNote->shipments;
        $items = [];

        foreach ($shipments as $shipment) {
            foreach ($order->transactions as $transaction) {
                $items[] = [
                    'orderItemId' => Arr::get($transaction->data, 'orderItemId'),
                    'quantity' => $transaction->quantity_ordered
                ];
            }

            $shipmentData = [
                'id' => $shipment->id,
                'tracking' => $shipment->tracking,
                'name' => $shipment->shipper->name,
                'items' => $items
            ];

            $amazonUser->confirmShipment($fulfillOrderId, $shipmentData);
        }
    }
}
