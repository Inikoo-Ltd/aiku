<?php

use App\Actions\Ordering\Order\CalculateOrderShipping;
use App\Models\Billables\ShippingZone;
use App\Models\Ordering\Order;

function makeShippingCalculator(): CalculateOrderShipping
{
    return CalculateOrderShipping::make();
}

function makeZone(array $price = [], ?array $shippersPrice = null): ShippingZone
{
    $zone                 = new ShippingZone();
    $zone->price          = $price;
    $zone->shippers_price = $shippersPrice;

    return $zone;
}

it('uses the zone default price when no per-shipper pricing is set', function () {
    $zone = makeZone(['type' => 'Step Order Items Net Amount', 'steps' => []]);

    expect(makeShippingCalculator()->getZonePriceDataForShipper($zone, null))
        ->toBe(['type' => 'Step Order Items Net Amount', 'steps' => []]);
});

it('uses the selected shipper price entry', function () {
    $dhl  = ['shipper_id' => 1, 'type' => 'Step Order Items Net Amount', 'steps' => [['from' => 0, 'to' => 'INF', 'price' => 9.95]]];
    $dpd  = ['shipper_id' => 2, 'type' => 'Step Order Estimated Weight', 'steps' => [['from' => 0, 'to' => 'INF', 'price' => 6.50]]];
    $zone = makeZone([], [$dhl, $dpd]);

    expect(makeShippingCalculator()->getZonePriceDataForShipper($zone, 2))->toBe($dpd);
});

it('falls back to the first shipper entry when none is selected or the selection is unknown', function () {
    $dhl  = ['shipper_id' => 1, 'type' => 'Step Order Items Net Amount', 'steps' => []];
    $dpd  = ['shipper_id' => 2, 'type' => 'TBC'];
    $zone = makeZone([], [$dhl, $dpd]);

    $calculator = makeShippingCalculator();

    expect($calculator->getZonePriceDataForShipper($zone, null))->toBe($dhl)
        ->and($calculator->getZonePriceDataForShipper($zone, 999))->toBe($dhl);
});

it('prices by items net amount steps', function () {
    $order               = new Order();
    $order->goods_amount = 100;

    $priceData = [
        'type'  => 'Step Order Items Net Amount',
        'steps' => [
            ['from' => 0, 'to' => 250, 'price' => 9.95],
            ['from' => 250, 'to' => 'INF', 'price' => 0],
        ]
    ];

    expect(makeShippingCalculator()->getShippingAmountFromPriceData($order, $priceData))->toBe(9.95);
});

it('prices by estimated weight steps in kilograms', function () {
    $order                   = new Order();
    $order->estimated_weight = 7000;

    $priceData = [
        'type'  => 'Step Order Estimated Weight',
        'steps' => [
            ['from' => 0, 'to' => 5, 'price' => 6.50],
            ['from' => 5, 'to' => 'INF', 'price' => 12],
        ]
    ];

    expect(makeShippingCalculator()->getShippingAmountFromPriceData($order, $priceData))->toEqual(12);
});

it('returns TBC for to-be-confirmed pricing', function () {
    expect(makeShippingCalculator()->getShippingAmountFromPriceData(new Order(), ['type' => 'TBC']))->toBe('TBC');
});
