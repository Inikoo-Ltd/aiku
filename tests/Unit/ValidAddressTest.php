<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Rules\ValidAddress;
use App\Models\Helpers\Country;

function addressFailures(array $address, bool $requireFullAddress): array
{
    $failures = [];
    (new ValidAddress(requireFullAddress: $requireFullAddress))->validate(
        'contact_address',
        $address,
        function ($message) use (&$failures) {
            $failures[] = (string)$message;
        }
    );

    return $failures;
}

test('an address with only a country still passes for imports and prospects', function () {
    $country = Country::where('code', 'GB')->firstOrFail();

    expect(addressFailures(['country_id' => $country->id], false))->toBeEmpty();
});

test('registration refuses an address with no street', function () {
    $country = Country::where('code', 'GB')->firstOrFail();

    expect(addressFailures(['country_id' => $country->id, 'locality' => 'Sheffield', 'postal_code' => 'S9 1XT'], true))
        ->toContain('The address is required')
        ->and(addressFailures(['country_id' => $country->id, 'address_line_1' => '  ', 'locality' => 'Sheffield', 'postal_code' => 'S9 1XT'], true))
        ->toContain('The address is required')
        ->and(addressFailures(['country_id' => $country->id, 'address_line_1' => '0', 'locality' => 'Sheffield', 'postal_code' => 'S9 1XT'], true))
        ->toContain('The address is required');
});

test('registration asks for what the country format requires, not a fixed set', function () {
    $uk    = Country::where('code', 'GB')->firstOrFail();
    $ie    = Country::where('code', 'IE')->firstOrFail();
    $uae   = Country::where('code', 'AE')->firstOrFail();
    $street = ['address_line_1' => 'Affinity Park'];

    /** The UK format wants a town and a postcode */
    expect(addressFailures(['country_id' => $uk->id] + $street, true))
        ->toContain('The town is required')
        ->toContain('The postal code is required');

    /** Ireland has no postal code in its format, so it must not be demanded */
    expect(addressFailures(['country_id' => $ie->id, 'locality' => 'Cork'] + $street, true))->toBeEmpty();

    /** The UAE has no town, it has an emirate */
    expect(addressFailures(['country_id' => $uae->id, 'administrative_area' => 'Dubai'] + $street, true))->toBeEmpty();
});

test('a complete address passes', function () {
    $country = Country::where('code', 'GB')->firstOrFail();

    expect(addressFailures([
        'country_id'     => $country->id,
        'address_line_1' => 'Affinity Park, Europa Drive',
        'locality'       => 'Sheffield',
        'postal_code'    => 'S9 1XT',
    ], true))->toBeEmpty();
});
