<?php

/*
 * author Arya Permana - Kirin
 * created on 25-06-2025-12h-32m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Tiktok\Order;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dropshipping\TiktokUser;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class FulfillOrderToTiktok extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(Order $order): void
    {
        $fulfillOrderId = $order->platform_order_id;

        if (! $order->customerSalesChannel->platform_status) {
            return;
        }

        /** @var TiktokUser $tiktokUser */
        $tiktokUser = $order->customerSalesChannel->user;

        /** @var DeliveryNote $deliveryNote */
        $deliveryNote = $order->deliveryNotes->first();
        $shipment = $deliveryNote->shipments()->first();

        $shippingProviders = $tiktokUser->getShippingProviders(Arr::get($order->data, 'tiktok_order.delivery_option_id'));
        $shippingProviderId = Arr::get($shippingProviders, 'data.shipping_providers.0.id');

        $tiktokUser->updateShippingInfo($fulfillOrderId, [
            'tracking_number' => (string) $shipment->tracking,
            'shipping_provider_id' => (string) $shippingProviderId
        ]);
    }
}
