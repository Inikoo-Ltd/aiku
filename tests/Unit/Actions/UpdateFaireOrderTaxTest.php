<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 18 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Catalogue\Shop\External\Faire\UpdateFaireOrder;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Currency;

function fakeGbpShop(): Shop
{
    $shop = new Shop();
    $shop->setRelation('currency', new Currency(['code' => 'GBP']));

    return $shop;
}

test('sums faire payout taxes into shop currency amount', function () {
    $orderFaireData = [
        'payout_costs' => [
            'taxes' => [
                ['tax_type' => 'VAT', 'value' => ['amount_minor' => 2148, 'currency' => 'GBP']],
            ],
        ],
    ];

    expect(UpdateFaireOrder::make()->getFaireTaxAmount($orderFaireData, fakeGbpShop()))->toBe(21.48);
});

test('sums multiple tax items', function () {
    $orderFaireData = [
        'payout_costs' => [
            'taxes' => [
                ['tax_type' => 'VAT', 'value' => ['amount_minor' => 1000, 'currency' => 'GBP']],
                ['tax_type' => 'RECARGO', 'value' => ['amount_minor' => 250, 'currency' => 'GBP']],
            ],
        ],
    ];

    expect(UpdateFaireOrder::make()->getFaireTaxAmount($orderFaireData, fakeGbpShop()))->toBe(12.50);
});

test('returns zero when faire order has no taxes', function () {
    expect(UpdateFaireOrder::make()->getFaireTaxAmount(['payout_costs' => []], fakeGbpShop()))->toBe(0.0);
});

test('reads the current store name from the faire order address', function () {
    expect(UpdateFaireOrder::make()->getFaireCompanyName(['address' => ['company_name' => ' The Hearth Studio ']]))->toBe('The Hearth Studio');
});

test('has no store name when the faire order address has none', function () {
    expect(UpdateFaireOrder::make()->getFaireCompanyName(['address' => ['company_name' => '']]))->toBeNull()
        ->and(UpdateFaireOrder::make()->getFaireCompanyName([]))->toBeNull();
});

function fakeFaireFundedOrder(string $countryCode): App\Models\Ordering\Order
{
    $shop           = new Shop();
    $shop->settings = ['faire' => ['funded_shipping_rates' => "SK 5.04 6.49\nfi 31,01 32,26\nnot a rate line"]];

    $order = new App\Models\Ordering\Order();
    $order->setRelation('shop', $shop);
    $order->setRelation('deliveryAddress', new App\Models\Helpers\Address(['country_code' => $countryCode]));

    return $order;
}

function fakeParcels(array $weights): App\Models\Dispatching\DeliveryNote
{
    $deliveryNote          = new App\Models\Dispatching\DeliveryNote();
    $deliveryNote->parcels = array_map(fn ($weight) => is_array($weight) ? $weight : ['weight' => $weight, 'dimensions' => [30, 20, 20]], $weights);

    return $deliveryNote;
}

test('faire funded shipping is the country rate per parcel by its weight band', function () {
    $action = App\Actions\Catalogue\Shop\External\Faire\UpdateShippingFaireOrder::make();
    $funded = ['free_shipping_reason' => 'INSIDER_FREE_SHIPPING'];

    expect($action->getFaireFundedShipping(fakeParcels([2]), fakeFaireFundedOrder('SK'), $funded))->toBe(5.04)
        ->and($action->getFaireFundedShipping(fakeParcels([3]), fakeFaireFundedOrder('SK'), $funded))->toBe(5.04)
        ->and($action->getFaireFundedShipping(fakeParcels([1.8, 4]), fakeFaireFundedOrder('FI'), $funded))->toBe(63.27)
        ->and($action->getFaireFundedShipping(fakeParcels([['weight' => 2, 'boxes' => 3], 7]), fakeFaireFundedOrder('SK'), $funded))->toBe(21.61);
});

test('no faire funded shipping on zone rate orders, unpriced countries or notes without parcels', function () {
    $action = App\Actions\Catalogue\Shop\External\Faire\UpdateShippingFaireOrder::make();
    $funded = ['free_shipping_reason' => 'INSIDER_FREE_SHIPPING'];

    expect($action->getFaireFundedShipping(fakeParcels([2]), fakeFaireFundedOrder('SK'), ['free_shipping_reason' => null]))->toBeNull()
        ->and($action->getFaireFundedShipping(fakeParcels([2]), fakeFaireFundedOrder('SK'), ['free_shipping_reason' => 'FREE_SHIPPING_THRESHOLD']))->toBeNull()
        ->and($action->getFaireFundedShipping(fakeParcels([2]), fakeFaireFundedOrder('CH'), $funded))->toBeNull()
        ->and($action->getFaireFundedShipping(fakeParcels([]), fakeFaireFundedOrder('SK'), $funded))->toBeNull();
});

test('faire funded shipping rates read one country per line', function () {
    expect(App\Actions\Catalogue\Shop\External\Faire\UpdateShippingFaireOrder::parseFundedShippingRates(" SK 5.04 6.49 \r\nfi 31,01 32,26\nCH 84.51\n"))
        ->toBe(['SK' => [5.04, 6.49], 'FI' => [31.01, 32.26]])
        ->and(App\Actions\Catalogue\Shop\External\Faire\UpdateShippingFaireOrder::parseFundedShippingRates(null))->toBe([]);
});
