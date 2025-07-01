<?php

/*
 * author Arya Permana - Kirin
 * created on 12-06-2025-09h-28m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Magento\Orders;

use App\Actions\OrgAction;
use App\Actions\Retina\Dropshipping\Client\Traits\WithGeneratedMagentoAddress;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dropshipping\MagentoUser;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class FulfillOrderToMagento extends OrgAction
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

        /** @var MagentoUser $magentoUser */
        $magentoUser = $order->customerSalesChannel->user;

        /** @var DeliveryNote $deliveryNote */
        $deliveryNote = $order->deliveryNotes->first();

        $shipmentData = [];
        $shipments = $deliveryNote->shipments;

        foreach ($shipments as $shipment) {
            $shipmentData[] = [
                'track_number' => $shipment->tracking,
                'title' => $shipment->shipper->name,
                'carrier_code' => $shipment->shipper->code
            ];
        }

        $magentoUser->updateOrderStatus($fulfillOrderId, 'complete', $shipmentData);
    }
}
