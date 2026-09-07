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
use App\Models\Fulfilment\PalletReturn;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Sentry;

class FulfillOrderToTiktok extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(Order|PalletReturn $order): ?array
    {
        if ($order->is_shipping_by_external) {
            return null;
        }

        $fulfillOrderId = $order->platform_order_id;

        if (! $order->customerSalesChannel->platform_status) {
            return null;
        }

        /** @var TiktokUser $tiktokUser */
        $tiktokUser = $order->customerSalesChannel->user;

        if ($order instanceof PalletReturn) {
            $shipment = $order->shipments()->first();
        } else {
            /** @var DeliveryNote|null $deliveryNote */
            $deliveryNote = $order->deliveryNotes->first();

            /** @var Shipment|null $shipment */
            $shipment = $deliveryNote?->shipments()->first();
        }

        if (!$shipment) {
            Sentry::captureMessage('TikTok order '.$fulfillOrderId.' dispatched without a shipment, tracking not sent');

            return null;
        }

        return $this->fulfillBySeller($tiktokUser, $fulfillOrderId, $order, $shipment);
    }

    public function fulfillBySeller(TiktokUser $tiktokUser, string $fulfillOrderId, Order|PalletReturn $order, Shipment $shipment): array
    {
        $deliveryOptionId = Arr::get($order->data, 'tiktok_order.delivery_option_id');
        $shippingProviders = blank($deliveryOptionId) ? [] : $tiktokUser->getShippingProviders((string) $deliveryOptionId);

        $shippingProvider = self::pickShippingProvider(
            Arr::get($shippingProviders, 'data.shipping_providers', []),
            $shipment->trade_as ?: $shipment->shipper?->trade_as ?: $shipment->shipper?->name
        );

        $response = $tiktokUser->updateShippingInfo($fulfillOrderId, [
            'tracking_number' => $shipment->tracking,
            'shipping_provider_id' => Arr::get($shippingProvider, 'id')
        ]);

        if (Arr::get($response, 'error') === true) {
            Sentry::captureMessage('TikTok rejected the tracking for order '.$fulfillOrderId.': '.Arr::get($response, 'data'));
        }

        return $response;
    }

    /**
     * @param  array<int, array{id: string, name: string}>  $shippingProviders
     * @return array{id: string, name: string}|null
     */
    public static function pickShippingProvider(array $shippingProviders, ?string $needle): ?array
    {
        if (blank($needle)) {
            return null;
        }

        return collect($shippingProviders)
            ->first(fn ($provider) => stripos((string) Arr::get($provider, 'name'), $needle) !== false);
    }
}
