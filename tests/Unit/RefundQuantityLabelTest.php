<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

test('refund quantity label shows packs, whole loose units, or hides artifacts', function (int|float $quantity, int|float|null $units, ?string $expected) {
    expect(refundQuantityLabel($quantity, $units))->toBe($expected);
})->with([
    // HELP-2913, refund GB2307163R: £1.81 of a £5.44 3-pack is one loose unit, not "-0.333"
    'one loose unit of a 3-pack'      => [-0.332721, 3, '-1/3'],
    'two loose units of a 3-pack'     => [-0.666667, 3, '-2/3'],
    'amount-refund of a whole pack'   => [-0.998457, 3, '-1'],
    'whole packs'                     => [-2, 3, '-2'],
    'five loose units of a 12-pack'   => [-0.416667, 12, '-5/12'],
    'arbitrary amount on a single'    => [-0.2, 1, null],
    'arbitrary amount on a 6-pack'    => [-0.100132, 6, null],
    'no pack size known'              => [-0.5, null, null],
]);
