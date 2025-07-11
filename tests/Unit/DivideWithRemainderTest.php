<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 11 Jul 2025 13:41:29 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace Tests\Unit;

test('divideWithRemainder returns [0, [dividend, divisor]] for zero divisor', function () {
    $result = divideWithRemainder([5, 0]);
    expect($result)->toBe([0, [5, 0]]);
});

test('divideWithRemainder works for normal division with remainder', function () {
    // Test for 10 / 3 = 3 with remainder 1
    $result = divideWithRemainder([10, 3]);
    expect($result)->toBe([3, [1, 3]]);

    // Test for 7 / 2 = 3 with remainder 1
    $result = divideWithRemainder([7, 2]);
    expect($result)->toBe([3, [1, 2]]);

    // Test for 20 / 6 = 3 with remainder 2
    $result = divideWithRemainder([20, 6]);
    expect($result)->toBe([3, [2, 6]]);
});

test('divideWithRemainder works for division with no remainder', function () {
    // Test for 10 / 5 = 2 with remainder 0
    $result = divideWithRemainder([10, 5]);
    expect($result)->toBe([2, [0, 5]]);

    // Test for 100 / 10 = 10 with remainder 0
    $result = divideWithRemainder([100, 10]);
    expect($result)->toBe([10, [0, 10]]);

    // Test for 0 / 5 = 0 with remainder 0
    $result = divideWithRemainder([0, 5]);
    expect($result)->toBe([0, [0, 5]]);
});

test('divideWithRemainder works for negative numbers', function () {
    // Test for -10 / 3 = -3 with remainder -1
    $result = divideWithRemainder([-10, 3]);
    expect($result)->toBe([-3, [-1, 3]]);

    // Test for 10 / -3 = -3 with remainder 1
    $result = divideWithRemainder([10, -3]);
    expect($result)->toBe([-3, [1, -3]]);

    // Test for -10 / -3 = 3 with remainder -1
    $result = divideWithRemainder([-10, -3]);
    expect($result)->toBe([3, [-1, -3]]);
});

test('divideWithRemainder works with large numbers', function () {
    // Test for 1000000 / 7 = 142857 with remainder 1
    $result = divideWithRemainder([1000000, 7]);
    expect($result)->toBe([142857, [1, 7]]);

    // Test for PHP_INT_MAX / 2 
    $result = divideWithRemainder([PHP_INT_MAX, 2]);
    expect($result[0])->toBe(intdiv(PHP_INT_MAX, 2));
    expect($result[1][0])->toBe(PHP_INT_MAX % 2);
    expect($result[1][1])->toBe(2);
});

test('divideWithRemainder handles edge cases', function () {
    // Test for 1 / 1 = 1 with remainder 0
    $result = divideWithRemainder([1, 1]);
    expect($result)->toBe([1, [0, 1]]);

    // Test for 0 / 1 = 0 with remainder 0
    $result = divideWithRemainder([0, 1]);
    expect($result)->toBe([0, [0, 1]]);

    // Test for the smallest possible dividend
    $result = divideWithRemainder([1, PHP_INT_MAX]);
    expect($result)->toBe([0, [1, PHP_INT_MAX]]);
});
