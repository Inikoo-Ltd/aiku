<?php

namespace App\Actions\Catalogue\Shop\External\Faire;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;

class UpdateFaireInventoryQuantity extends OrgAction
{
    public function handle(Shop $shop, Order $order): array
    {
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
    }
}
