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

test('our shipping zones become Shopify zones with weight rates, postcode zones fold into the main country price and TBC never blocks checkout', function () {
    $zones = [
        shippingZoneForProfile('Zone 2', [['country_code' => 'GB', 'included_postal_codes' => '/^BT/']], weightSteps([['from' => 0, 'to' => 'INF', 'price' => 14.5]])),
        shippingZoneForProfile('Zone 3', [['country_code' => 'IM'], ['country_code' => 'GB', 'included_postal_codes' => '/^HS/']], weightSteps([['from' => 0, 'to' => 2, 'price' => 2.99], ['from' => 2, 'to' => 'INF', 'price' => 14.5]])),
        shippingZoneForProfile('Rest of the world', [], ['type' => 'TBC']),
        shippingZoneForProfile('Zone 1', [['country_code' => 'GB']], weightSteps([
            ['from' => 0, 'to' => 0.1, 'price' => 2.79],
            ['from' => 0.1, 'to' => 2, 'price' => 2.99],
            ['from' => 2, 'to' => 'INF', 'price' => 5.99],
        ])),
        shippingZoneForProfile('Zone 6', [['country_code' => 'US']], weightSteps([['from' => 0, 'to' => 0.5, 'price' => 12.5], ['from' => 2, 'to' => 'INF', 'price' => 'TBC']])),
    ];

    $result = SyncShopifyDeliveryProfile::zonesToCreate($zones, 'GBP', fn ($amount) => number_format((float) $amount, 2, '.', ''));

    expect(array_column($result, 'name'))->toBe(['Zone 3', 'Zone 1', 'Zone 6', 'Rest of the world'])
        ->and($result[0]['countries'])->toBe([['code' => 'IM', 'includeAllProvinces' => true]])
        ->and($result[1]['countries'])->toBe([['code' => 'GB', 'includeAllProvinces' => true]])
        ->and($result[1]['methodDefinitionsToCreate'])->toBe([
            [
                'name'                     => 'Standard',
                'active'                   => true,
                'rateDefinition'           => ['price' => ['amount' => '2.79', 'currencyCode' => 'GBP']],
                'weightConditionsToCreate' => [['criteria' => ['value' => 0.1, 'unit' => 'KILOGRAMS'], 'operator' => 'LESS_THAN_OR_EQUAL_TO']],
            ],
            [
                'name'                     => 'Standard',
                'active'                   => true,
                'rateDefinition'           => ['price' => ['amount' => '2.99', 'currencyCode' => 'GBP']],
                'weightConditionsToCreate' => [
                    ['criteria' => ['value' => 0.101, 'unit' => 'KILOGRAMS'], 'operator' => 'GREATER_THAN_OR_EQUAL_TO'],
                    ['criteria' => ['value' => 2.0, 'unit' => 'KILOGRAMS'], 'operator' => 'LESS_THAN_OR_EQUAL_TO'],
                ],
            ],
            [
                'name'                     => 'Standard',
                'active'                   => true,
                'rateDefinition'           => ['price' => ['amount' => '5.99', 'currencyCode' => 'GBP']],
                'weightConditionsToCreate' => [['criteria' => ['value' => 2.001, 'unit' => 'KILOGRAMS'], 'operator' => 'GREATER_THAN_OR_EQUAL_TO']],
            ],
        ])
        ->and(array_column(array_column($result[2]['methodDefinitionsToCreate'], 'rateDefinition'), 'price'))->toBe([
            ['amount' => '12.50', 'currencyCode' => 'GBP'],
            ['amount' => '12.50', 'currencyCode' => 'GBP'],
        ])
        ->and($result[3]['countries'])->toBe([['restOfWorld' => true]])
        ->and($result[3]['methodDefinitionsToCreate'][0]['rateDefinition']['price']['amount'])->toBe('14.50');
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
