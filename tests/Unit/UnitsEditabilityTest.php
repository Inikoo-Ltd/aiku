<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Masters\MasterAsset\StoreMasterAsset;
use App\Models\Goods\TradeUnit;
use App\Models\Masters\MasterAsset;
use Illuminate\Database\Eloquent\Relations\MorphPivot;

function unitsCandidate(bool $independent, array $quantities): MasterAsset
{
    $masterAsset                        = new MasterAsset();
    $masterAsset->units                 = 6;
    $masterAsset->has_independent_units = $independent;
    $masterAsset->setRelation('tradeUnits', collect($quantities)->map(function ($quantity) {
        $tradeUnit = new TradeUnit();
        $tradeUnit->setRelation('pivot', new MorphPivot(['quantity' => $quantity]));

        return $tradeUnit;
    }));

    return $masterAsset;
}

beforeEach(function () {
    $this->action = app(StoreMasterAsset::class);
});

it('shows nothing for a single trade unit, which states its own pack size', function () {
    expect($this->action->getUnitsEditability(unitsCandidate(false, [6])))
        ->toBe(['shown' => false, 'canToggle' => false, 'byHand' => false])
        ->and($this->action->getUnitsField(unitsCandidate(false, [6])))->toBeNull();
});

it('forces units by hand, with nothing to opt out of, when the quantities disagree', function () {
    expect($this->action->getUnitsEditability(unitsCandidate(false, [6, 12])))
        ->toBe(['shown' => true, 'canToggle' => false, 'byHand' => true]);
});

it('offers the opt out when every trade unit packs the same', function () {
    expect($this->action->getUnitsEditability(unitsCandidate(false, [6, 6])))
        ->toBe(['shown' => true, 'canToggle' => true, 'byHand' => false])
        ->and($this->action->getUnitsEditability(unitsCandidate(true, [6, 6])))
        ->toBe(['shown' => true, 'canToggle' => true, 'byHand' => true]);
});

it('carries the opt out beside the units so both save together', function () {
    $field = $this->action->getUnitsField(unitsCandidate(true, [6, 6]));

    expect($field['type'])->toBe('composition_units')
        ->and($field['hasOther'])->toBe(['name' => 'has_independent_units', 'value' => true]);
});
