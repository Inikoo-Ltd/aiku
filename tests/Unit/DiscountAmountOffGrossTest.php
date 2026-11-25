<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

test('a discount lands on the same cent the basket quoted', function (float $gross, float $factor, float $expected) {
    expect(discountAmountOffGross($gross, $factor))->toBe($expected);
})->with([
    // Every one of these puts the discount on an exact half cent, where a float complement
    // rounds whichever way its binary error happens to fall. 0.9 is the factor that broke
    // HELP-2981; the rest broke under the first attempt at fixing it.
    'ten per cent, PL30364'    => [61.95, 0.9, 6.20],
    'ten per cent, small'      => [0.35, 0.9, 0.04],
    'thirty per cent'          => [0.75, 0.7, 0.23],
    'five per cent'            => [0.70, 0.95, 0.04],
    'fifteen per cent'         => [1.50, 0.85, 0.23],
    'thirty five per cent'     => [0.10, 0.65, 0.04],
    'no discount'              => [61.95, 1.0, 0.0],
    'a refund line'            => [-61.95, 0.9, -6.20],
]);

test('a part picked line discounts the gross it actually has, sub cent included', function () {
    // A part pick leaves a sub cent gross. Rounding it to the cent first would make this 5.01.
    expect(discountAmountOffGross(10.005, 0.5))->toBe(5.00);
});

test('a line split in two keeps the discount of the single line it was', function () {
    // HELP-4953: 10 x £3.49 at 5% sold as one line, picked as 5 + 5 of a substitute.
    expect(discountAmountOffGross(34.90, 0.95))->toBe(1.75)
        ->and(discountAmountsOffGrossGroup([1 => 17.45, 2 => 17.45], 0.95))->toBe([1 => 0.87, 2 => 0.88])
        ->and(discountAmountsOffGrossGroup([7 => 61.95], 0.9))->toBe([7 => 6.20]);
});
