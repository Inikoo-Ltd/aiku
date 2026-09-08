<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

test('a sold line keeps the pack size it was sold in', function (int|float|null $historic, int|float|null $product, int|float|null $expected) {
    expect(soldPackUnits($historic, $product))->toBe($expected);
})->with([
    // HELP-2983, invoice PLi263211: CDes-20 was sold as a single and the product became a pack
    // of 4 the next day. Testing the historic value with `> 1` read "sold as a single" as
    // "not set" and relabelled the line 4x, a pack size that was never part of that sale.
    'sold as a single, product later a pack' => [1.0, 4.0, 1.0],
    'sold as a pack, product later bigger'   => [3.0, 6.0, 3.0],
    'sold as a pack, product later a single' => [6.0, 1.0, 6.0],
    'composition unchanged'                  => [4.0, 4.0, 4.0],
    'no historic composition recorded'       => [null, 4.0, 4.0],
    'neither known'                          => [null, null, null],
]);
