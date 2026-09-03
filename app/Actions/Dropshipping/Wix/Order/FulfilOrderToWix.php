<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\Order;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dispatching\Shipment;
use App\Models\Dropshipping\WixUser;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Sentry;

class FulfilOrderToWix extends RetinaAction
{
    use WithActionUpdate;

    public function handle(Order $order): void
    {
        /** @var WixUser $wixUser */
        $wixUser = $order->customerSalesChannel?->user;

        if (!$wixUser instanceof WixUser || !$order->platform_order_id) {
            return;
        }

        try {
            $deliveryNote = $order->deliveryNotes->first();

            /** @var Shipment $shipment */
            $shipment = $deliveryNote?->shipments()->first();

            $lineItems = $order->transactions
                ->whereNotNull('platform_transaction_id')
                ->map(fn ($transaction) => [
                    'id'       => $transaction->platform_transaction_id,
                    'quantity' => (int) $transaction->quantity_dispatched ?: (int) $transaction->quantity_ordered,
                ])
                ->values()
                ->all();

            $fulfillment = [
                'lineItems' => $lineItems,
                'status'    => 'Fulfilled'
            ];

            if ($shipment?->tracking) {
                $fulfillment['trackingInfo'] = [
                    'trackingNumber' => $shipment->tracking,
                    'shippingProvider' => $shipment->shipper?->trade_as ?: 'other'
                ];

                if($shipment->combined_label_url) {
                    $fulfillment['trackingInfo']['trackingLink'] = $shipment->combined_label_url;
                }
            }

            $wixUser->createOrderFulfillment($order->platform_order_id, $fulfillment);
        } catch (\Exception $e) {
            Sentry::captureException($e);
        }
    }

    public string $commandSignature = 'wix:fulfil_order {order}';

    public function asCommand(Command $command)
    {
        $order = Order::where('slug', $command->argument('order'))->firstOrFail();
        $this->handle($order);
    }
}
