<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 02 Aug 2026 23:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Models\Catalogue\Product;

test('a lone wildcard no longer matches every row', function (string $macro) {
    $everything = Product::count();

    expect($everything)->toBeGreaterThan(1);

    $matches = Product::where(fn ($query) => $query->{$macro}('products.name', '%'))->count();

    expect($matches)->toBeLessThan($everything);
})->with(['whereWith', 'whereStartWith', 'whereEndWith', 'whereAnyWordStartWith']);

test('an underscore matches an underscore, not any character', function () {
    $product = Product::first();
    $probe   = mb_substr($product->name, 0, 1).'_'.mb_substr($product->name, 2, 1);

    $matches = Product::where(fn ($query) => $query->whereWith('products.name', $probe))->count();

    expect($matches)->toBe(
        Product::where('products.name', 'ILIKE', '%'.str_replace('_', '\_', $probe).'%')->count()
    );
});

test('ordinary search terms still match', function () {
    $product = Product::first();

    expect($product)->not->toBeNull();

    $found = Product::where(fn ($query) => $query->whereWith('products.name', $product->name))
        ->where('products.id', $product->id)
        ->exists();

    expect($found)->toBeTrue();
});
