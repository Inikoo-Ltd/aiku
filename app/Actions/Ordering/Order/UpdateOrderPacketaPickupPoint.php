<?php

namespace App\Actions\Ordering\Order;

use App\Actions\Catalogue\PreferredShipping\WithPreferredShipperResolver;
use App\Actions\Dispatching\Shipment\ApiCalls\CallApiPacketaShipping;
use App\Actions\Dispatching\Shipment\ApiCalls\FindPacketaPickupPoint;
use App\Models\Dispatching\Shipper;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * HELP-4946. A customer ordering by hand chooses the Packeta pickup point or Z-BOX on Packeta's
 * map. Only the point id comes from the browser: it is checked against Packeta's list, and the
 * order keeps aiku's own copy of the point. The choice is offered only when the order would go
 * with Packeta, and it then locks the delivery note to Packeta, so a heavier parcel is not
 * handed to another courier for the home address.
 */
class UpdateOrderPacketaPickupPoint
{
    use AsAction;
    use WithPreferredShipperResolver;

    public const string DATA_KEY = 'packeta_pickup_point';

    /**
     * @throws ValidationException
     */
    public function handle(Order $order, ?string $pointId): Order
    {
        if (!$pointId) {
            DB::update('update orders set data = data - ? where id = ?', [self::DATA_KEY, $order->id]);

            return $order->refresh();
        }

        $points = FindPacketaPickupPoint::make()->getPoints();
        if (!$points) {
            throw ValidationException::withMessages(['pickup_point_id' => __('The list of Packeta pickup points is not available right now, please try again in a few minutes.')]);
        }

        $point = $points[$pointId] ?? null;
        if (!$point) {
            throw ValidationException::withMessages(['pickup_point_id' => __('This Packeta pickup point was not found, please choose another one.')]);
        }

        if ($error = $this->getPointError($order, $point)) {
            throw ValidationException::withMessages(['pickup_point_id' => $error]);
        }

        DB::update(
            "update orders set data = jsonb_set(coalesce(data, '{}'::jsonb), '{".self::DATA_KEY."}', ?::jsonb) where id = ?",
            [json_encode(Arr::only($point, ['id', 'type', 'name', 'street', 'city', 'zip', 'country', 'max_weight'])), $order->id]
        );

        return $order->refresh();
    }

    /**
     * @param  array<string, mixed>  $point
     */
    public function getPointError(Order $order, array $point): ?string
    {
        if (!$this->getPacketaShipperIdForOrder($order)) {
            return __('Delivery to a Packeta pickup point is not available for this order. Remove the pickup point to send it to the delivery address.');
        }

        $countryCode = (string) $order->deliveryAddress?->country_code;
        if ($point['country'] !== strtolower($countryCode)) {
            return __('This Packeta pickup point is not in the country of the delivery address, please choose one in :country.', ['country' => $order->deliveryAddress?->country?->name ?? $countryCode]);
        }

        $weight = round(((int) $order->estimated_weight + self::BOX_WEIGHT_ALLOWANCE) / 1000, 1);
        if ($point['max_weight'] && $weight > $point['max_weight']) {
            return __('This order weighs about :weight kg and this pickup point takes parcels up to :max kg.', ['weight' => $weight, 'max' => $point['max_weight']]);
        }

        return null;
    }

    /**
     * The order goes with Packeta when the customer locked Packeta, or when the shipping rules
     * would pick Packeta for its country, postcode and estimated weight.
     */
    public function getPacketaShipperIdForOrder(Order $order): ?int
    {
        $address = $order->deliveryAddress;
        if (!$address?->country_id || !in_array(strtolower((string) $address->country_code), FindPacketaPickupPoint::make()->getCountries())) {
            return null;
        }

        $packetaShipperId = $this->getPacketaShipperId($order->organisation_id);
        if (!$packetaShipperId) {
            return null;
        }

        $shipperId = $order->is_shipper_locked && $order->shipper_id
            ? $order->shipper_id
            : $this->pickPreferredShipperId(
                $this->getPreferredShippingRules($order->organisation_id, $order->shop_id, $order->shop?->type, (bool) $order->is_premium_dispatch),
                $address->country_id,
                (string) $address->postal_code,
                (int) $order->estimated_weight + self::BOX_WEIGHT_ALLOWANCE
            );

        return $shipperId == $packetaShipperId ? $packetaShipperId : null;
    }

    public function getPacketaShipperId(int $organisationId): ?int
    {
        return Shipper::where('organisation_id', $organisationId)->where('api_shipper', 'packeta-sk')->where('status', true)->value('id');
    }

    /**
     * @return array{api_key: string|null, country: string, selected: array<string, mixed>|null, error: string|null}|null
     */
    public function getBasketOption(Order $order): ?array
    {
        $selected         = data_get($order->data, self::DATA_KEY);
        $packetaShipperId = $this->getPacketaShipperIdForOrder($order);
        if (!$packetaShipperId && !$selected) {
            return null;
        }

        return [
            'api_key'  => $packetaShipperId ? Arr::get(CallApiPacketaShipping::make()->getAccessToken(Shipper::find($packetaShipperId)), 'api_key') : null,
            'country'  => strtolower((string) $order->deliveryAddress?->country_code),
            'selected' => $selected,
            'error'    => $selected ? $this->getPointError($order, $selected) : null,
        ];
    }
}
