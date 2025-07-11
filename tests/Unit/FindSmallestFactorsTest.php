<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 10 Jul 2025 18:37:38 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace Tests\Unit;

test('findSmallestFactors returns [0, 1] for zero input', function () {
    $result = findSmallestFactors(0.0);
    expect($result)->toBe([0, 1]);
});

test('findSmallestFactors works for numbers less than 1', function () {
    // Test for 0.5 (1/2)
    $result = findSmallestFactors(0.5);
    expect($result)->toBe([1, 2]);

    // Test for 0.25 (1/4)
    $result = findSmallestFactors(0.25);
    expect($result)->toBe([1, 4]);

    // Test for 0.33333... (1/3)
    $result = findSmallestFactors(1 / 3);
    expect($result)->toBe([1, 3]);

    // Test for 0.66666... (2/3)
    $result = findSmallestFactors(2 / 3);
    expect($result)->toBe([2, 3]);
});

test('findSmallestFactors works for numbers greater than 1', function () {
    // Test for 2.0 (2/1)
    $result = findSmallestFactors(2.0);
    expect($result)->toBe([1, 2]);

    // Test for 3.5 (7/2)
    $result = findSmallestFactors(3.5);
    expect($result)->toBe([7, 2]);

    // Test for 1.5 (3/2)
    $result = findSmallestFactors(1.5);
    expect($result)->toBe([3, 2]);
});

test('findSmallestFactors works for negative numbers', function () {
    // Test for -0.5 (-1/2)
    $result = findSmallestFactors(-0.5);
    expect($result)->toBe([-1, 2]);

    // Test for -2.0 (-2/1)
    $result = findSmallestFactors(-2.0);
    expect($result)->toBe([-1, 2]);
});

test('findSmallestFactors works for complex fractions', function () {
    // Test for 0.142857... (1/7)
    $result = findSmallestFactors(1 / 7);
    expect($result)->toBe([1, 7]);

    // Test for 0.333333... (1/3)
    $result = findSmallestFactors(1 / 3);
    expect($result)->toBe([1, 3]);

    // Test for 0.090909... (1/11)
    $result = findSmallestFactors(1 / 11);
    expect($result)->toBe([1, 11]);

    // Test for 0.16600 (1/6) with a larger epsilon
    $result = findSmallestFactors(0.16600, 0.001);
    expect($result)->toBe([1, 6]);

    // Test for 0.16667 (1/6)
    $result = findSmallestFactors(0.16667);
    expect($result)->toBe([1, 6]);

});

test('findSmallestFactors works with custom epsilon', function () {
    // Test with a larger epsilon for less precision
    $result = findSmallestFactors(0.333, 0.001);
    expect($result)->toBe([1, 3]);


    $result = findSmallestFactors(0.333, 0.0001);
    expect($result)->toBe([1, 3]);
});

test('findSmallestFactors handles edge cases', function () {
    // Test for a very small number
    $result = findSmallestFactors(0.001);
    expect($result)->toBe([1, 100]);

    // Test for a very large number
    $result = findSmallestFactors(1000.0);
    expect($result)->toBe([1, 1000]);

    // Test for a number very close to an integer
    $result = findSmallestFactors(2.0001);
    expect($result)->toBe([2, 1]);
});
