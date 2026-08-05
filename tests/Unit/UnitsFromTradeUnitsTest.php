<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Traits\WithMasterAssetTradeUnits;

$resolver = new class () {
    use WithMasterAssetTradeUnits;
};

it('takes the quantity of a single trade unit', function () use ($resolver) {
    expect($resolver->getUnitsFromTradeUnits([['id' => 1, 'quantity' => 6, 'type' => 'piece']]))
        ->toBe(['units' => 6.0, 'unit' => 'piece']);
});

it('takes the shared quantity when every trade unit packs the same', function () use ($resolver) {
    expect($resolver->getUnitsFromTradeUnits([
        ['id' => 1, 'quantity' => 6, 'type' => 'piece'],
        ['id' => 2, 'quantity' => 6, 'type' => 'piece'],
    ]))->toBe(['units' => 6.0, 'unit' => 'bundle']);
});

it('claims no units when the trade unit quantities differ, leaving a hand set value alone', function () use ($resolver) {
    expect($resolver->getUnitsFromTradeUnits([
        ['id' => 1, 'quantity' => 6, 'type' => 'piece'],
        ['id' => 2, 'quantity' => 12, 'type' => 'piece'],
    ]))->toBe(['units' => null, 'unit' => 'bundle']);
});

it('claims no pack size for a discovery pack of one of each', function () use ($resolver) {
    expect($resolver->getSharedTradeUnitQuantity([
        ['id' => 1, 'quantity' => 1],
        ['id' => 2, 'quantity' => 1],
        ['id' => 3, 'quantity' => 1],
    ]))->toBeNull();
});

it('falls back to one when there are no trade units', function () use ($resolver) {
    expect($resolver->getUnitsFromTradeUnits([]))->toBe(['units' => 1.0, 'unit' => null]);
});
