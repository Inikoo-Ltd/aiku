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
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class FulfillOrderToEbay extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(Order $order): array|null
    {
        $fulfillOrderId = Arr::get($order->data, 'ebay_order.orderId');

        if (! $fulfillOrderId) {
            $fulfillOrderId = $order->platform_order_id;
        }

        if (! $order->customerSalesChannel->platform_status) {
            return [];
        }

        /** @var EbayUser $ebayUser */
        $ebayUser = $order->customerSalesChannel->user;

        /** @var DeliveryNote|null $deliveryNote */
        $deliveryNote = $order->deliveryNotes->first();

        $shipment  = $deliveryNote?->shipments()->first();
        $lineItems = [];

        foreach ($order->transactions()->where('model_type', 'Product')->get() as $transaction) {
            if ((int) $transaction->quantity_dispatched > 0) {
                $lineItems[] = [
                    'lineItemId' => $transaction->platform_transaction_id,
                    'quantity' => (int) $transaction->quantity_dispatched,
                ];
            }
        }

        return $ebayUser->fulfillOrder($fulfillOrderId, [
            'line_items'      => $lineItems,
            'tracking_number' => $shipment?->tracking,
            'carrier_code'    => $this->carrierCode($ebayUser, $shipment?->shipper?->name)
        ]);
    }

    /**
     * eBay links tracking to a carrier only when it gets its own carrier code, so a shipper name that matches
     * one of the marketplace carriers is translated; anything else is passed through as it was before.
     */
    public function carrierCode(EbayUser $ebayUser, ?string $shipperName): ?string
    {
        if (blank($shipperName)) {
            return null;
        }

        $carrier = collect($ebayUser->getServicesWithCarrierInfo())->first(
            fn ($service) => strcasecmp($service['carrier_name'], $shipperName) === 0 || strcasecmp($service['carrier_code'], $shipperName) === 0
        );

        return $carrier['carrier_code'] ?? $shipperName;
    }

    public string $commandSignature = 'ebay-order-fulfill {order}';

    public function asCommand(Command $command): void
    {
        $order = Order::where('slug', $command->argument('order'))->first();

        $result = $this->handle($order);

        $command->table(['Field', 'Value'], [
            ['Order ID', $order->id],
            ['Status', Arr::get($result, 'error', 'Success')],
        ]);
    }
}
