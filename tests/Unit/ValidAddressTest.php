<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Rules\ValidAddress;
use App\Models\Helpers\Country;

function addressFailures(array $address, bool $requireStreet): array
{
    $failures = [];
    (new ValidAddress(requireStreet: $requireStreet))->validate(
        'contact_address',
        $address,
        function ($message) use (&$failures) {
            $failures[] = (string)$message;
        }
    );

    return $failures;
}

test('an address with only a country still passes for imports and prospects', function () {
    $country = Country::first();

    expect(addressFailures(['country_id' => $country->id], false))->toBeEmpty();
});

test('registration refuses an address with no street', function () {
    $country = Country::first();

    expect(addressFailures(['country_id' => $country->id], true))->not->toBeEmpty()
        ->and(addressFailures(['country_id' => $country->id, 'address_line_1' => ''], true))->not->toBeEmpty()
        ->and(addressFailures(['country_id' => $country->id, 'address_line_1' => '  '], true))->not->toBeEmpty()
        ->and(addressFailures(['country_id' => $country->id, 'address_line_1' => '0'], true))->not->toBeEmpty();
});

test('registration accepts an address with a street', function () {
    $country = Country::first();

    expect(addressFailures(['country_id' => $country->id, 'address_line_1' => '31 Bradley Road'], true))->toBeEmpty();
});
