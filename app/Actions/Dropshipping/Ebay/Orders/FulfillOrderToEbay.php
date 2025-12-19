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
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class FulfillOrderToEbay extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(Order $order): void
    {
        $fulfillOrderId = Arr::get($order->data, 'ebay_order.orderId');

        if (! $fulfillOrderId) {
            $fulfillOrderId = $order->platform_order_id;
        }

        if (! $order->customerSalesChannel->platform_status) {
            return;
        }

        /** @var EbayUser $ebayUser */
        $ebayUser = $order->customerSalesChannel->user;

        /** @var DeliveryNote $deliveryNote */
        $deliveryNote = $order->deliveryNotes->first();

        $shipment = $deliveryNote->shipments()->first();
        $lineItems = [];

        foreach ($order->transactions()->where('model_type', 'Product')->get() as $transaction) {
            if((int) $transaction->quantity_dispatched > 0) {
                $lineItems[] = [
                    'lineItemId' => $transaction->platform_transaction_id,
                    'quantity' => (int) $transaction->quantity_dispatched,
                ];
            }
        }

        $ebayUser->fulfillOrder($fulfillOrderId, [
            'line_items' => $lineItems,
            'tracking_number' => $shipment->tracking,
            'carrier_code' => $shipment->shipper->name
        ]);

        Log::info('Order Fulfilled to Ebay:' . $fulfillOrderId);
    }
}
