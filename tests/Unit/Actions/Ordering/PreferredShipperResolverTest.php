<?php

use App\Actions\Catalogue\PreferredShipping\WithPreferredShipperResolver;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\PreferredShipping;

function makePreferredShipperResolver()
{
    return new class () {
        use WithPreferredShipperResolver;
    };
}

function makeRule(int $shipperId, ?int $countryId = null, ?string $postcode = null, bool $important = false): PreferredShipping
{
    $rule             = new PreferredShipping();
    $rule->shipper_id = $shipperId;
    $rule->country_id = $countryId;
    $rule->postcode   = $postcode;
    $rule->important  = $important;

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
