<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Mar 2025 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Allegro\Order;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dispatching\Shipment;
use App\Models\Dropshipping\AllegroUser;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;

class FulfilOrderToAllegro extends RetinaAction
{
    use WithActionUpdate;

    public function handle(Order $order): void
    {
        /** @var AllegroUser $allegroUser */
        $allegroUser = $order->customerSalesChannel->user;

        $carrierId = null;
        $deliveryNote = $order->deliveryNotes->first();
        /** @var Shipment $shipment */
        $shipment = $deliveryNote->shipments()->first();

        try {
            $carriers = $allegroUser->getCarriers();
            collect(Arr::get($carriers, 'carriers'))->each(function ($carrier) use (&$carrierId, $shipment) {
                if ($carrier['id'] === $shipment->shipper->trade_as) {
                    $carrierId = $carrier['id'];
                }
            });

            if(!$carrierId) {
                $carrierId = 'OTHER';
            }

            $allegroUser->addOrderTracking($order->platform_order_id, [
                'carrier_id' => $carrierId,
                'waybill' => $shipment->tracking,
                'carrier_name' => $shipment->shipper->trade_as,
                'line_items' => $order->transactions->pluck('platform_transaction_id')->toArray()
            ]);
        } catch (\Exception $e) {
            \Sentry::captureMessage($e);
        }
    }
}
