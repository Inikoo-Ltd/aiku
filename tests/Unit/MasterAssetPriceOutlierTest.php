<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Masters\MasterAsset\GetMasterAssetPriceOutlier;

test('price per unit far from the family median is an outlier, HELP-3496', function () {
    expect(GetMasterAssetPriceOutlier::run(157.19, 1, 9.5)['times'])->toBe(16.5)
        ->and(GetMasterAssetPriceOutlier::run(3, 1, 10)['times'])->toBe(0.3)
        ->and(GetMasterAssetPriceOutlier::run(10.8, 1, 9.5))->toBeNull()
        ->and(GetMasterAssetPriceOutlier::run(60, 6, 10))->toBeNull()
        ->and(GetMasterAssetPriceOutlier::run(157.19, 1, null))->toBeNull()
        ->and(GetMasterAssetPriceOutlier::run(0, 1, 10))->toBeNull();
});

test('the family median is taken from the master prices of a currency when one is given, and from the scalar price otherwise', function () {
    expect(GetMasterAssetPriceOutlier::familyUnitPriceMedianSql('GBP'))->toContain("siblings.master_prices->'GBP'->>'value'")
        ->and(GetMasterAssetPriceOutlier::familyUnitPriceMedianSql())->toContain('order by siblings.price / siblings.units')
        ->and(GetMasterAssetPriceOutlier::familyUnitPriceMedianSql("x'; drop"))->toContain('order by siblings.price / siblings.units');
});
