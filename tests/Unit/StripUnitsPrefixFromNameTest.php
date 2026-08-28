<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Maintenance\Masters\RepairMasterProductUnits;
use App\Models\Goods\TradeUnit;
use App\Models\Masters\MasterAsset;

beforeEach(function () {
    $this->repair = app(RepairMasterProductUnits::class);
});

it('removes the pack size prefix when it matches the units', function () {
    expect($this->repair->stripUnitsPrefixFromName('192x 10ml Frosted Green Glass Dropper Bottle', 192))
        ->toBe('10ml Frosted Green Glass Dropper Bottle');
});

it('keeps dimensions that are not a leading pack size', function () {
    expect($this->repair->stripUnitsPrefixFromName('8x Linen Cushion Covers - with fringe 45x45cm', 8))
        ->toBe('Linen Cushion Covers - with fringe 45x45cm')
        ->and($this->repair->stripUnitsPrefixFromName('Medium Ball Top Terarium - 17x22cm', 17))
        ->toBe('Medium Ball Top Terarium - 17x22cm');
});

it('leaves the name alone when the prefix is a different quantity', function () {
    expect($this->repair->stripUnitsPrefixFromName('51x Gift Boxed Votive Candles', 20))
        ->toBe('51x Gift Boxed Votive Candles');
});

function assortmentCandidate(string $code, int $tradeUnitCount): MasterAsset
{
    $masterAsset = new MasterAsset();
    $masterAsset->code = $code;
    $masterAsset->setRelation(
        'tradeUnits',
        collect(range(1, $tradeUnitCount))->map(fn () => new TradeUnit())
    );

    return $masterAsset;
}

it('treats a starter pack code as an assortment whatever its composition', function () {
    expect($this->repair->isAssortment(assortmentCandidate('Stamford-ST', 93)))->toBeTrue()
        ->and($this->repair->isAssortment(assortmentCandidate('drkwhit-st', 14)))->toBeTrue()
        ->and($this->repair->isAssortment(assortmentCandidate('FFB-St', 9)))->toBeTrue();
});

it('treats many distinct trade units as an assortment even when the code hides it', function () {
    expect($this->repair->isAssortment(assortmentCandidate('IncenST-02', 18)))->toBeTrue()
        ->and($this->repair->isAssortment(assortmentCandidate('NoteB-ST2', 16)))->toBeTrue();
});

it('keeps component sets, which carry a real pack size', function () {
    expect($this->repair->isAssortment(assortmentCandidate('FGDB-05AC', 2)))->toBeFalse()
        ->and($this->repair->isAssortment(assortmentCandidate('LinCST-01', 2)))->toBeFalse()
        ->and($this->repair->isAssortment(assortmentCandidate('QSalt-12X', 3)))->toBeFalse();
});

it('passes a null name through', function () {
    expect($this->repair->stripUnitsPrefixFromName(null, 6))->toBeNull();
});
