<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace Tests\Unit;

use App\Actions\Catalogue\Product\Hydrators\ProductHydrateMarketingWeightFromTradeUnits;

test('single trade unit net weight is the share of the trade unit each unit holds', function (?int $tradeUnitWeight, ?string $quantity, ?string $units, ?int $expected) {
    expect(ProductHydrateMarketingWeightFromTradeUnits::getSingleTradeUnitMarketingWeight($tradeUnitWeight, $quantity, $units))->toBe($expected);
})->with([
    'slice of a 1.2 kg loaf'             => [1200, '0.10000000', '1.000', 120],
    'bar from a 13 bar loaf'             => [1300, '0.07700000', '1.000', 100],
    '10 ml tester of a 1 kg oil'         => [1000, '0.01000000', '0.010', 10],
    '250 g of a 1 kg oil'                => [1000, '0.25000000', '0.250', 250],
    'pack of 10 sold as one'             => [39, '10.00000000', '1.000', 390],
    'pack of 12 sold as 12 units'        => [15, '12.00000000', '12.000', 15],
    'yield allowance counts as whole'    => [500, '0.96000000', '1.000', 500],
    'float noise on a whole trade unit'  => [160, '0.99999999', '1.000', 160],
    'no quantity keeps trade unit'       => [160, null, '1.000', 160],
    'no trade unit weight'               => [null, '0.10000000', '1.000', null],
]);
