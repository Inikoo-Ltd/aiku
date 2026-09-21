<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Shipment;

use App\Actions\Dispatching\Shipment\ApiCalls\CallApiApcGbShipping;
use App\Models\Dispatching\Shipment;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class FetchShipmentLabel
{
    use AsAction;

    /**
     * APC builds the label after the order is accepted, so it can still be empty when the shipment is stored.
     */
    public function handle(Shipment $shipment): Shipment
    {
        if ($shipment->label || $shipment->combined_label_url) {
            return $shipment;
        }

        if ($shipment->shipper?->api_shipper != 'apc-gb') {
            return $shipment;
        }

        $orderNumber = Arr::get($shipment->api_response, 'Orders.Order.OrderNumber');
        if (!$orderNumber) {
            return $shipment;
        }

        $label = CallApiApcGbShipping::make()->getLabel($orderNumber, $shipment->shipper);
        if ($label) {
            $shipment->update(['label' => $label]);
        }

        return $shipment;
    }
}
