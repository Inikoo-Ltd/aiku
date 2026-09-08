<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 02 Aug 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Models\Helpers\Country;

test('search terms with regex metacharacters do not break the query', function (string $term) {
    expect(Country::where(fn ($query) => $query->whereAnyWordStartWith('countries.name', $term))->count())
        ->toBeInt();
})->with([
    'unbalanced parenthesis'  => 'rose (large',
    'windows path'            => 'C:\WINDOWS\system32\drivers\etc\hosts',
    'traversal'               => '../329289',
    'quantifier'              => 'a{2,',
    'character class'         => 'tea [green]',
    'alternation and anchors' => '^rose|jasmine$',
    'star and plus'           => '**+?',
]);

test('escaping keeps the search matching real words', function () {
    $country = Country::first();

    expect($country)->not->toBeNull();

    $found = Country::where(fn ($query) => $query->whereAnyWordStartWith('countries.name', $country->name))
        ->where('countries.id', $country->id)
        ->exists();

    expect($found)->toBeTrue();
});
