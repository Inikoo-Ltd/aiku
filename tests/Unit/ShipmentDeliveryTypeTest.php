<?php

use App\Actions\Dispatching\Shipment\GetShipmentDeliveryType;
use App\Enums\Dispatching\Shipment\ShipmentDeliveryTypeEnum;

test('shipment delivery type is read from the platform order and the address', function (array $orderData, ?string $countryCode, array $addressLines, array $notes, ShipmentDeliveryTypeEnum $type, ?string $code) {
    expect(GetShipmentDeliveryType::make()->resolve($orderData, $countryCode, $addressLines, $notes))
        ->toBe(['delivery_type' => $type, 'pickup_point_code' => $code]);
})->with([
    'allegro paczkomat'          => [['allegro_order' => ['delivery' => ['pickupPoint' => ['id' => 'KRA01M', 'name' => 'Paczkomat KRA01M']]]], 'PL', ['Słowackiego 209', null], [], ShipmentDeliveryTypeEnum::PICKUP_POINT, 'KRA01M'],
    'allegro courier'            => [['allegro_order' => ['delivery' => ['pickupPoint' => null]]], 'PL', ['Słowackiego 209', null], [], ShipmentDeliveryTypeEnum::HOME, null],
    'ebay inpost locker'         => [['ebay_order' => ['fulfillmentStartInstructions' => [['fulfillmentInstructionsType' => 'PREPARE_FOR_PICKUP']]]], 'GB', ['24/7 InPost Locker - Best One', null], [], ShipmentDeliveryTypeEnum::PICKUP_POINT, null],
    'ebay home'                  => [['ebay_order' => ['fulfillmentStartInstructions' => [['fulfillmentInstructionsType' => 'SHIP_TO']]]], 'GB', ['12 High Street', null], [], ShipmentDeliveryTypeEnum::HOME, null],
    'woo inpost paczkomat'       => [['woo_order' => ['shipping_lines' => [['method_id' => 'easypack_parcel_machines']]]], 'PL', ['Paczkomat NAM01BAPP', null], [], ShipmentDeliveryTypeEnum::PICKUP_POINT, 'NAM01BAPP'],
    'woo omniva terminal'        => [['woo_order' => ['shipping_lines' => [['method_id' => 'multiparcels_omniva_lv_pickup_point']]]], 'LV', ['Brivibas 1', null], [], ShipmentDeliveryTypeEnum::PICKUP_POINT, null],
    'woo merchant click collect' => [['woo_order' => ['shipping_lines' => [['method_id' => 'local_pickup']]]], 'GB', ['22 Redhoave Road', null], [], ShipmentDeliveryTypeEnum::HOME, null],
    'woo flat rate'              => [['woo_order' => ['shipping_lines' => [['method_id' => 'flat_rate']]]], 'ES', ['Calle Mayor 3', null], [], ShipmentDeliveryTypeEnum::HOME, null],
    'shopify merchant message'   => [['shopify_data' => []], 'PL', ['Rataja 17A', null], ['Pickup point delivery: InPost Paczkomat OLS10BAPP, Rataja 17A, 10-203 Olsztyn'], ShipmentDeliveryTypeEnum::PICKUP_POINT, 'OLS10BAPP'],
    'packeta z-box in note'      => [[], 'CZ', ['Vinohradská 12', null], ['Z-BOX Praha 2'], ShipmentDeliveryTypeEnum::PICKUP_POINT, null],
    'orlen paczka partner point' => [[], 'PL', ['ORLEN Paczka Punkt partnerski ORLEN Paczka 569329', 'Dąbrowskiego 1'], [], ShipmentDeliveryTypeEnum::PICKUP_POINT, null],
    'orlen paczka code'          => [[], 'PL', ['Orlen Paczka WS-509800-F0-05', 'Gościniec 9a'], [], ShipmentDeliveryTypeEnum::PICKUP_POINT, null],
    'pocztex point in zabka'     => [[], 'PL', ['Punkcie Pocztex Sklep Żabka 765842', 'Wasilkowska 22B'], [], ShipmentDeliveryTypeEnum::PICKUP_POINT, null],
    'pocztex zabka no punkt'     => [[], 'PL', ['Pocztex Sklep Żabka 748630', 'Osiedle Zwycięstwa 125'], [], ShipmentDeliveryTypeEnum::PICKUP_POINT, null],
    'punkt odbioru'              => [[], 'PL', ['Polna 4', null], ['Proszę dostarczyć do punktu odbioru'], ShipmentDeliveryTypeEnum::PICKUP_POINT, null],
    'own zabka shop is home'     => [[], 'PL', ['Kosynierów', '13/15'], ['Do sklepu żabka'], ShipmentDeliveryTypeEnum::HOME, null],
    'po box is home'             => [[], 'GB', ['PO Box 123', null], ['leave the box at the door'], ShipmentDeliveryTypeEnum::HOME, null],
    'courier pickup note'        => [[], 'SK', ['Hlavná 5', null], ['courier pickup at 17:00'], ShipmentDeliveryTypeEnum::HOME, null],
]);
