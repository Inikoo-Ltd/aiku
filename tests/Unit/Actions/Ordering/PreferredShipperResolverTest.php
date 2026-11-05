<?php

use App\Actions\Catalogue\PreferredShipping\WithPreferredShipperResolver;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\PreferredShipping;
use App\Models\Dispatching\DeliveryNote;

function makePreferredShipperResolver()
{
    return new class () {
        use WithPreferredShipperResolver;
    };
}

function makeRule(int $shipperId, ?int $countryId = null, ?string $postcode = null, bool $important = false, ?int $minWeight = null, ?int $maxWeight = null): PreferredShipping
{
    $rule             = new PreferredShipping();
    $rule->shipper_id = $shipperId;
    $rule->country_id = $countryId;
    $rule->postcode   = $postcode;
    $rule->important  = $important;
    $rule->min_weight = $minWeight;
    $rule->max_weight = $maxWeight;

    return $rule;
}

it('picks the most specific matching rule over a wildcard', function () {
    $rules = collect([
        makeRule(shipperId: 1),
        makeRule(shipperId: 2, countryId: 10),
        makeRule(shipperId: 3, countryId: 10, postcode: 'SW1'),
    ]);

    $resolver = makePreferredShipperResolver();

    expect($resolver->pickPreferredShipperId($rules, 10, 'SW1A1AA'))->toBe(3)
        ->and($resolver->pickPreferredShipperId($rules, 10, 'EC1A1BB'))->toBe(2)
        ->and($resolver->pickPreferredShipperId($rules, 99, 'X'))->toBe(1);
});

it('important wins only when its territory matches', function () {
    $rules = collect([
        makeRule(shipperId: 1, countryId: 10, postcode: 'SW1'),
        makeRule(shipperId: 2, countryId: 20, important: true),
    ]);

    $resolver = makePreferredShipperResolver();

    expect($resolver->pickPreferredShipperId($rules, 20, ''))->toBe(2)
        ->and($resolver->pickPreferredShipperId($rules, 10, 'SW1A1AA'))->toBe(1);
});

it('important beats a more specific non-important rule in the same territory', function () {
    $rules = collect([
        makeRule(shipperId: 1, countryId: 10, postcode: 'SW1'),
        makeRule(shipperId: 2, countryId: 10, important: true),
    ]);

    expect(makePreferredShipperResolver()->pickPreferredShipperId($rules, 10, 'SW1A1AA'))->toBe(2);
});

it('returns the winning rule so a lock can be told apart from a preference', function () {
    $rules = collect([
        makeRule(shipperId: 1, countryId: 10),
        makeRule(shipperId: 2, countryId: 20, important: true),
    ]);

    $resolver = makePreferredShipperResolver();

    expect($resolver->pickPreferredShippingRule($rules, 20, '')->important)->toBeTrue()
        ->and($resolver->pickPreferredShippingRule($rules, 10, '')->important)->toBeFalse()
        ->and($resolver->pickPreferredShippingRule($rules, 99, ''))->toBeNull();
});

it('maps dropshipping shops to the b2c rule set and everything else to b2b', function () {
    $resolver = makePreferredShipperResolver();

    expect($resolver->tradeScopeForShopType(ShopTypeEnum::DROPSHIPPING))->toBe('b2c')
        ->and($resolver->tradeScopeForShopType(ShopTypeEnum::B2C))->toBe('b2c')
        ->and($resolver->tradeScopeForShopType(ShopTypeEnum::B2B))->toBe('b2b')
        ->and($resolver->tradeScopeForShopType(ShopTypeEnum::FULFILMENT))->toBe('b2b')
        ->and($resolver->tradeScopeForShopType(null))->toBe('b2b');
});

it('matches any of several comma-separated postcode prefixes', function () {
    $rules = collect([
        makeRule(shipperId: 1, countryId: 10, postcode: '91, 93,67'),
    ]);

    $resolver = makePreferredShipperResolver();

    expect($resolver->pickPreferredShipperId($rules, 10, '93100'))->toBe(1)
        ->and($resolver->pickPreferredShipperId($rules, 10, '67000'))->toBe(1)
        ->and($resolver->pickPreferredShipperId($rules, 10, '75001'))->toBeNull();
});

it('matches postcode prefixes ignoring spaces and case', function () {
    $rules = collect([
        makeRule(shipperId: 1, countryId: 10, postcode: 'sw1 a'),
    ]);

    $resolver = makePreferredShipperResolver();

    expect($resolver->pickPreferredShipperId($rules, 10, 'SW1A 1AA'))->toBe(1)
        ->and($resolver->pickPreferredShipperId($rules, 10, 'EC1A 1BB'))->toBeNull();
});

it('a shop or priority-only rule beats a country rule, and a lock still beats both', function () {
    $countryRule = makeRule(shipperId: 1, countryId: 10);
    $shopRule = makeRule(shipperId: 2);
    $shopRule->shop_id = 7;
    $priorityRule = makeRule(shipperId: 3);
    $priorityRule->shop_id = 7;
    $priorityRule->priority_only = true;

    $resolver = makePreferredShipperResolver();

    expect($resolver->pickPreferredShipperId(collect([$countryRule, $shopRule]), 10, ''))->toBe(2)
        ->and($resolver->pickPreferredShipperId(collect([$countryRule, $shopRule, $priorityRule]), 10, ''))->toBe(3)
        ->and($resolver->pickPreferredShipperId(collect([makeRule(shipperId: 4, countryId: 10, important: true), $priorityRule]), 10, ''))->toBe(4);
});

it('picks the carrier by weight band, lower bound included and upper bound excluded like the Packeta tiers, falling back to the catch-all between bands', function () {
    $rules = collect([
        makeRule(shipperId: 1),
        makeRule(shipperId: 2, countryId: 10, minWeight: 250, maxWeight: 2000),
        makeRule(shipperId: 3, countryId: 10, minWeight: 5000),
    ]);

    $resolver = makePreferredShipperResolver();

    expect($resolver->pickPreferredShipperId($rules, 10, '', 100))->toBe(1)
        ->and($resolver->pickPreferredShipperId($rules, 10, '', 249))->toBe(1)
        ->and($resolver->pickPreferredShipperId($rules, 10, '', 250))->toBe(2)
        ->and($resolver->pickPreferredShipperId($rules, 10, '', 1500))->toBe(2)
        ->and($resolver->pickPreferredShipperId($rules, 10, '', 2000))->toBe(1)
        ->and($resolver->pickPreferredShipperId($rules, 10, '', 5000))->toBe(3)
        ->and($resolver->pickPreferredShipperId($rules, 10, '', 3000))->toBe(1)
        ->and($resolver->pickPreferredShipperId($rules, 10, '', 31000))->toBe(3)
        ->and($resolver->pickPreferredShipperId($rules, 20, '', 31000))->toBe(1);
});

it('a weight band wins over a country rule but not over a postcode rule', function () {
    $rules = collect([
        makeRule(shipperId: 1, countryId: 10),
        makeRule(shipperId: 2, countryId: 10, maxWeight: 2000),
        makeRule(shipperId: 3, countryId: 10, postcode: 'SW1'),
    ]);

    $resolver = makePreferredShipperResolver();

    expect($resolver->pickPreferredShipperId($rules, 10, 'EC1', 1000))->toBe(2)
        ->and($resolver->pickPreferredShipperId($rules, 10, 'EC1', 3000))->toBe(1)
        ->and($resolver->pickPreferredShipperId($rules, 10, 'SW1A', 1000))->toBe(3);
});

it('adds a box allowance to the estimated weight but takes a weight typed at the warehouse, or weighed boxes, as they are', function () {
    $resolver = makePreferredShipperResolver();

    $estimatedOnly = new DeliveryNote();
    $estimatedOnly->estimated_weight = 1800;

    $weighed = new DeliveryNote();
    $weighed->estimated_weight = 1800;
    $weighed->weight = 1900;

    $boxesWeighed = new DeliveryNote();
    $boxesWeighed->estimated_weight = 1800;
    $boxesWeighed->parcels = [['weight' => 0.6, 'dimensions' => [20, 20, 15]], ['weight' => 1.45, 'dimensions' => [30, 20, 15]]];

    $boxesFilledFromEstimate = new DeliveryNote();
    $boxesFilledFromEstimate->estimated_weight = 1800;
    $boxesFilledFromEstimate->parcels = [['weight' => 1.8, 'dimensions' => [5, 5, 5]]];

    expect($resolver->weightForShipperChoice($estimatedOnly))->toBe(2300)
        ->and($resolver->weightForShipperChoice($weighed))->toBe(1900)
        ->and($resolver->weightForShipperChoice($boxesWeighed))->toBe(2050)
        ->and($resolver->weightForShipperChoice($boxesFilledFromEstimate))->toBe(2300);
});
