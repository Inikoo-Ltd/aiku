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
use App\Models\Dispatching\Shipment;
use App\Models\Dropshipping\TiktokUser;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class FulfillOrderToTiktok extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(Order $order): void
    {
        if($order->is_shipping_by_external) {
            return;
        }

        $fulfillOrderId = $order->platform_order_id;

        if (! $order->customerSalesChannel->platform_status) {
            return;
        }

        /** @var TiktokUser $tiktokUser */
        $tiktokUser = $order->customerSalesChannel->user;

        /** @var DeliveryNote $deliveryNote */
        $deliveryNote = $order->deliveryNotes->first();

        /** @var Shipment $shipment */
        $shipment = $deliveryNote->shipments()->first();

        $this->fulfillBySeller($tiktokUser, $fulfillOrderId, $order, $shipment);
    }

    public function fulfillBySeller(TiktokUser $tiktokUser, string $fulfillOrderId, Order $order, Shipment $shipment): void
    {
        $shippingProviders = $tiktokUser->getShippingProviders(Arr::get($order->data, 'tiktok_order.delivery_option_id'));

        /** @var array $shippingProvider */
        $shippingProvider = collect(Arr::get($shippingProviders, 'data.shipping_providers', []))
            ->first(fn($provider) => stripos(Arr::get($provider, 'name'), $shipment->trade_as) !== false);

        $tiktokUser->updateShippingInfo($fulfillOrderId, [
            'tracking_number' => $shipment->tracking,
            'shipping_provider_id' => Arr::get($shippingProvider, 'id')
        ]);
    }
}
