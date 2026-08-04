<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 02 Aug 2026 23:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Models\Helpers\Country;

test('a lone wildcard no longer matches every row', function (string $macro) {
    $everything = Country::count();

    expect($everything)->toBeGreaterThan(1);

    $matches = Country::where(fn ($query) => $query->{$macro}('countries.name', '%'))->count();

    expect($matches)->toBeLessThan($everything);
})->with(['whereWith', 'whereStartWith', 'whereEndWith', 'whereAnyWordStartWith']);

test('an underscore matches an underscore, not any character', function () {
    $country = Country::first();
    $probe   = mb_substr($country->name, 0, 1).'_'.mb_substr($country->name, 2, 1);

    $matches = Country::where(fn ($query) => $query->whereWith('countries.name', $probe))->count();

    expect($matches)->toBe(
        Country::where('countries.name', 'ILIKE', '%'.str_replace('_', '\_', $probe).'%')->count()
    );
});

test('ordinary search terms still match', function () {
    $country = Country::first();

    expect($country)->not->toBeNull();

    $found = Country::where(fn ($query) => $query->whereWith('countries.name', $country->name))
        ->where('countries.id', $country->id)
        ->exists();

    expect($found)->toBeTrue();
});
