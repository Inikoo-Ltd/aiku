<?php

namespace App\Actions\Dispatching\Shipment;

use App\Actions\Dispatching\Shipment\ApiCalls\CallApiPacketaShipping;
use App\Enums\Dispatching\Shipment\ShipmentDeliveryTypeEnum;
use App\Models\Dispatching\DeliveryNote;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Whether a shipment goes to the end customer's home or to a parcel locker / pickup point
 * (HELP-4769). Platforms that say so in the order are trusted first: Allegro pickupPoint,
 * eBay PREPARE_FOR_PICKUP and the WooCommerce locker shipping methods. Otherwise the address
 * and notes are read, the same way the Packeta booking finds Polish InPost lockers, plus a
 * few unambiguous locker words. A bare "box" or "pickup" is never enough: PO boxes and
 * courier collections would count as lockers.
 */
class GetShipmentDeliveryType
{
    use AsObject;

    private const string WOO_PICKUP_METHOD_PATTERN = '/parcel_machine|pickup_point|parcel_locker|paczkomat|parcelshop/i';

    private const string LOCKER_TEXT_PATTERN = '/paczkomat|parcel\s*locker|inpost\s*locker|z-?box\b|pickup\s*point|parcel\s*shop|packstation|v[ýy]dejn|odb[eě]rn[éeý]\s*m[íi]st|pakiautomaat|orlen\s*paczk|punk\w*\s+(odbioru|partnersk|pocztex)|pocztex\s*\(?\s*(sklep\s+)?[żz]abka/iu';

    /**
     * @return array{delivery_type: ShipmentDeliveryTypeEnum, pickup_point_code: string|null}
     */
    public function handle(DeliveryNote $deliveryNote): array
    {
        $address = $deliveryNote->deliveryAddress;

        return $this->resolve(
            (array) $deliveryNote->orders->first()?->data,
            $address?->country_code,
            [$address?->address_line_1, $address?->address_line_2],
            [$deliveryNote->shipping_notes, $deliveryNote->customer_notes],
        );
    }

    /**
     * @param  array<string, mixed>  $orderData
     * @param  array<int, string|null>  $addressLines
     * @param  array<int, string|null>  $notes
     *
     * @return array{delivery_type: ShipmentDeliveryTypeEnum, pickup_point_code: string|null}
     */
    public function resolve(array $orderData, ?string $countryCode, array $addressLines, array $notes): array
    {
        if ($chosenPointId = data_get($orderData, 'packeta_pickup_point.id')) {
            return $this->pickupPoint((string) $chosenPointId);
        }

        $lockerCode = (new CallApiPacketaShipping())->getParcelLockerCode((string) $countryCode, 0, $addressLines, $notes);

        $allegroPickupPoint = data_get($orderData, 'allegro_order.delivery.pickupPoint');
        if ($allegroPickupPoint) {
            return $this->pickupPoint(data_get($allegroPickupPoint, 'id') ?? $lockerCode);
        }

        if (data_get($orderData, 'ebay_order.fulfillmentStartInstructions.0.fulfillmentInstructionsType') === 'PREPARE_FOR_PICKUP') {
            return $this->pickupPoint($lockerCode);
        }

        foreach ((array) data_get($orderData, 'woo_order.shipping_lines', []) as $shippingLine) {
            if (preg_match(self::WOO_PICKUP_METHOD_PATTERN, (string) data_get($shippingLine, 'method_id'))) {
                return $this->pickupPoint($lockerCode);
            }
        }

        if ($lockerCode || preg_match(self::LOCKER_TEXT_PATTERN, implode(' ', array_filter([...$addressLines, ...$notes])))) {
            return $this->pickupPoint($lockerCode);
        }

        return [
            'delivery_type'     => ShipmentDeliveryTypeEnum::HOME,
            'pickup_point_code' => null,
        ];
    }

    /**
     * @return array{delivery_type: ShipmentDeliveryTypeEnum, pickup_point_code: string|null}
     */
    private function pickupPoint(?string $code): array
    {
        return [
            'delivery_type'     => ShipmentDeliveryTypeEnum::PICKUP_POINT,
            'pickup_point_code' => $code ? mb_substr($code, 0, 255) : null,
        ];
    }
}
