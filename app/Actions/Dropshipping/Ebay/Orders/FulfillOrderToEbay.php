<?php

/*
 * author Arya Permana - Kirin
 * created on 25-06-2025-12h-32m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Ebay\Orders;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dropshipping\EbayUser;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class FulfillOrderToEbay extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(Order $order): void
    {
        $fulfillOrderId = Arr::get($order->data, 'orderId');

        /** @var EbayUser $ebayUser */
        $ebayUser = $order->customerSalesChannel->user;

        /** @var DeliveryNote $deliveryNote */
        $deliveryNote = $order->deliveryNotes->first();

        $shipment = $deliveryNote->shipments()->first();
        $lineItems = [];

        foreach ($order->transactions as $transaction) {
            $lineItems[] = [
                'lineItemId' => Arr::get($transaction->data, 'lineItemId'),
                'quantity' => $transaction->quantity_dispatched,
            ];
        }

        $ebayUser->fulfillOrder($fulfillOrderId, [
            'lineItems' => $lineItems,
            'shippedDate' => now()->timestamp,
            'tracking_number' => $shipment->tracking,
            'carrier_code' => $shipment->shipper->name,
            'tracking_number' => $shipment->tracking
        ]);
    }
}
