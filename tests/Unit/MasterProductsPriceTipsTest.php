<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Masters\MasterAsset\Json\GetMasterProductsPriceTips;

test('overstocked product selling less than last year gets a markdown sized by cover', function () {
    expect(GetMasterProductsPriceTips::tip(200, 400, 80, 100, 10, 2)['change'])->toBe(-5)
        ->and(GetMasterProductsPriceTips::tip(400, 500, 80, 100, 10, 2)['change'])->toBe(-10)
        ->and(GetMasterProductsPriceTips::tip(730, 730, 0, 100, 10, 2)['change'])->toBe(-15);
});

test('markdown never takes the price below cost plus the minimum markup', function () {
    expect(GetMasterProductsPriceTips::tip(730, 730, 0, 100, 10, 7.6)['change'])->toBe(-5)
        ->and(GetMasterProductsPriceTips::tip(730, 730, 0, 100, 10, 7.9))->toBeNull();
});

test('overstock in one organisation only is not a slow mover', function () {
    expect(GetMasterProductsPriceTips::tip(20, 730, 50, 100, 10, 2))->toBeNull();
});

test('product running out everywhere with growing sales gets a markup', function () {
    expect(GetMasterProductsPriceTips::tip(5, 10, 120, 100, 10, 2)['change'])->toBe(8)
        ->and(GetMasterProductsPriceTips::tip(5, 20, 120, 100, 10, 2)['change'])->toBe(5)
        ->and(GetMasterProductsPriceTips::tip(5, 20, 90, 100, 10, 2))->toBeNull()
        ->and(GetMasterProductsPriceTips::tip(0, 0, 120, 100, 10, 2))->toBeNull();
});

test('products without sales a year ago never get a tip', function () {
    expect(GetMasterProductsPriceTips::tip(730, 730, 0, 0, 10, 2))->toBeNull()
        ->and(GetMasterProductsPriceTips::tip(5, 10, 50, 0, 10, 2))->toBeNull();
});
