<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 30 Oct 2026 12:00:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace Tests\Unit\Dropshipping;

use App\Actions\Dropshipping\Shopify\FulfilmentService\SyncShopifyDeliveryProfile;
use App\Models\Billables\ShippingZone;

function shippingZoneForProfile(string $name, array $territories, array $price): ShippingZone
{
    return new ShippingZone(['name' => $name, 'territories' => $territories, 'price' => $price]);
}

function weightSteps(array $steps): array
{
    return ['type' => 'Step Order Estimated Weight', 'steps' => $steps];
}

test('our shipping zones become Shopify zones with weight rates, a country covered by postcode takes its zone without postcode rules and TBC gets no rate', function () {
    $ukSteps = fn (float $heavy) => weightSteps([
        ['from' => 0, 'to' => 0.1, 'price' => 2.79],
        ['from' => 0.1, 'to' => 2, 'price' => 2.99],
        ['from' => 2, 'to' => 'INF', 'price' => $heavy],
    ]);
    $zones = [
        shippingZoneForProfile('Zone 2', [['country_code' => 'GB', 'included_postal_codes' => '/^BT/']], $ukSteps(14.5)),
        shippingZoneForProfile('Zone 3', [['country_code' => 'IM'], ['country_code' => 'GB', 'included_postal_codes' => '/^HS/']], $ukSteps(14.5)),
        shippingZoneForProfile('Rest of the world', [], ['type' => 'TBC']),
        shippingZoneForProfile('Zone 1', [['country_code' => 'GB']], $ukSteps(5.99)),
        shippingZoneForProfile('Zone 4', [['country_code' => 'FR']], weightSteps([
            ['from' => 0, 'to' => 2, 'price' => 10],
            ['from' => 2, 'to' => 5, 'price' => 10.5],
            ['from' => 2, 'to' => 30, 'price' => 18.5],
            ['from' => 30, 'to' => 'INF', 'price' => 'TBC'],
        ])),
        shippingZoneForProfile('Zone 6', [['country_code' => 'US']], weightSteps([['from' => 0, 'to' => 0.5, 'price' => 12.5], ['from' => 0.5, 'to' => 'INF', 'price' => 'TBC']])),
    ];

    $result = SyncShopifyDeliveryProfile::zonesToCreate($zones, 'GBP', fn ($amount) => number_format((float) $amount, 2, '.', ''));
    $prices = fn (array $zone) => array_column(array_column(array_column($zone['methodDefinitionsToCreate'], 'rateDefinition'), 'price'), 'amount');
    $weights = fn (array $zone) => array_map(fn (array $method) => array_map(fn (array $condition) => [$condition['operator'], $condition['criteria']['value']], $method['weightConditionsToCreate'] ?? []), $zone['methodDefinitionsToCreate']);

    expect(array_column($result, 'name'))->toBe(['Zone 3', 'Zone 1', 'Zone 4', 'Zone 6'])
        ->and($result[0]['countries'])->toBe([['code' => 'IM', 'includeAllProvinces' => true]])
        ->and($result[1]['countries'])->toBe([['code' => 'GB', 'includeAllProvinces' => true]])
        ->and($prices($result[1]))->toBe(['2.79', '2.99', '5.99'])
        ->and($result[0]['methodDefinitionsToCreate'][0])->toBe([
            'name'                     => 'AW Standard',
            'active'                   => true,
            'rateDefinition'           => ['price' => ['amount' => '2.79', 'currencyCode' => 'GBP']],
            'weightConditionsToCreate' => [['criteria' => ['value' => 0.1, 'unit' => 'KILOGRAMS'], 'operator' => 'LESS_THAN_OR_EQUAL_TO']],
        ])
        ->and($prices($result[0]))->toBe(['2.79', '2.99', '14.50'])
        ->and($weights($result[0]))->toBe([
            [['LESS_THAN_OR_EQUAL_TO', 0.1]],
            [['GREATER_THAN_OR_EQUAL_TO', 0.101], ['LESS_THAN_OR_EQUAL_TO', 2.0]],
            [['GREATER_THAN_OR_EQUAL_TO', 2.001]],
        ])
        ->and($prices($result[2]))->toBe(['10.00', '10.50', '18.50'])
        ->and($weights($result[2]))->toBe([
            [['LESS_THAN_OR_EQUAL_TO', 2.0]],
            [['GREATER_THAN_OR_EQUAL_TO', 2.001], ['LESS_THAN_OR_EQUAL_TO', 5.0]],
            [['GREATER_THAN_OR_EQUAL_TO', 5.001], ['LESS_THAN_OR_EQUAL_TO', 30.0]],
        ])
        ->and($prices($result[3]))->toBe(['12.50'])
        ->and($weights($result[3]))->toBe([[['LESS_THAN_OR_EQUAL_TO', 0.5]]]);
});

test('a priced rest of the world becomes the rest of world zone and prices go through the store currency', function () {
    $zones = [
        shippingZoneForProfile('Rest of the world', [], weightSteps([['from' => 0, 'to' => 'INF', 'price' => 20]])),
        shippingZoneForProfile('España', [['country_code' => 'ES', 'excluded_postal_codes' => '/^(35|38)/']], weightSteps([['from' => 0, 'to' => 'INF', 'price' => 2.95]])),
    ];

    $result = SyncShopifyDeliveryProfile::zonesToCreate($zones, 'USD', fn ($amount) => number_format((float) $amount * 2, 2, '.', ''));

    expect($result[0]['countries'])->toBe([['code' => 'ES', 'includeAllProvinces' => true]])
        ->and($result[0]['methodDefinitionsToCreate'][0]['rateDefinition']['price'])->toBe(['amount' => '5.90', 'currencyCode' => 'USD'])
        ->and($result[0]['methodDefinitionsToCreate'][0])->not->toHaveKey('weightConditionsToCreate')
        ->and($result[1]['countries'])->toBe([['restOfWorld' => true]]);
});

test('italy takes its plain zone rates, not the islands or remote areas surcharge, and a country only by postcode takes its cheapest zone', function () {
    $zones = [
        shippingZoneForProfile('Italy - remote areas', [['country_code' => 'IT', 'included_postal_codes' => '/^(22060|23030)$/']], weightSteps([['from' => 0, 'to' => 'INF', 'price' => 73.9]])),
        shippingZoneForProfile('Italy - islands', [['country_code' => 'IT', 'included_postal_codes' => '/^(07|08|09|9[0-8])/']], weightSteps([['from' => 0, 'to' => 'INF', 'price' => 27.3]])),
        shippingZoneForProfile('Zone 24 (Italy)', [['country_code' => 'IT']], weightSteps([['from' => 0, 'to' => 'INF', 'price' => 8.9]])),
        shippingZoneForProfile('Canarias', [['country_code' => 'ES', 'included_postal_codes' => '/^(35|38)/']], weightSteps([['from' => 0, 'to' => 'INF', 'price' => 30]])),
        shippingZoneForProfile('Peninsula', [['country_code' => 'ES', 'excluded_postal_codes' => '/^(35|38)/']], weightSteps([['from' => 0, 'to' => 'INF', 'price' => 6]])),
    ];

    $result = SyncShopifyDeliveryProfile::zonesToCreate($zones, 'EUR', fn ($amount) => number_format((float) $amount, 2, '.', ''));

    expect(array_column($result, 'name'))->toBe(['Zone 24 (Italy)', 'Peninsula'])
        ->and($result[0]['countries'])->toBe([['code' => 'IT', 'includeAllProvinces' => true]])
        ->and($result[0]['methodDefinitionsToCreate'][0]['rateDefinition']['price']['amount'])->toBe('8.90')
        ->and($result[1]['countries'])->toBe([['code' => 'ES', 'includeAllProvinces' => true]]);
});

test('a country the shop does not deliver to gets no Shopify zone, and a zone left with no country is dropped', function () {
    $zones = [
        shippingZoneForProfile('Zona 4', [['country_code' => 'AT'], ['country_code' => 'CH']], weightSteps([['from' => 0, 'to' => 'INF', 'price' => 9]])),
        shippingZoneForProfile('Zona 5', [['country_code' => 'GR']], weightSteps([['from' => 0, 'to' => 'INF', 'price' => 12]])),
    ];

    $result = SyncShopifyDeliveryProfile::zonesToCreate($zones, 'EUR', fn ($amount) => number_format((float) $amount, 2, '.', ''), ['CH', 'GR']);

    expect(array_column($result, 'name'))->toBe(['Zona 4'])
        ->and($result[0]['countries'])->toBe([['code' => 'AT', 'includeAllProvinces' => true]]);
});
