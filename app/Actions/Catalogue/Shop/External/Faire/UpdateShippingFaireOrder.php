<?php

namespace App\Actions\Catalogue\Shop\External\Faire;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Sentry;

class UpdateShippingFaireOrder extends OrgAction
{
    public function handle(Shop $shop, Order $order): array
    {
        try {
            $shipments = [];
            foreach ($order->deliveryNotes as $deliveryNote) {
                foreach ($deliveryNote->shipments as $shipment) {
                    $shipments[] = [
                        'shipping_type' => 'SHIP_ON_YOUR_OWN',
                        'order_id' => $order->external_id,
                        'carrier'  => $shipment->shipper?->name,
                        'tracking_code' => $shipment->tracking
                    ];
                }
            }

            return $shop->updateShippingFaireOrder($order->external_id, [
                'shipments' => $shipments
            ]);
        } catch (\Exception $e) {
            Sentry::captureException($e);
            return [];
        }
    }

    public function asCommand(Command $command): void
    {
        $this->handle(Shop::where('slug', $command->argument('shop'))->first(), Order::where('slug', $command->argument('order'))->first());
    }
}
