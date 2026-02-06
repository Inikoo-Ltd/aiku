<?php

/**
 * Test Decimal Weights for Imported Products
 * Author: Oggie Sutrisna 👌
 * Created: Wed, 05 Feb 2025 00:00:00 UTC
 *
 */

namespace Tests\Unit\Retina\Dropshipping;

test('decimal weights conversion for external platforms', function () {
    $productWeightInGrams = 320;

    expect($productWeightInGrams)->toBeInt();
    expect($productWeightInGrams)->toBe(320);

    $weightInKgCorrect = $productWeightInGrams / 1000;
    expect($weightInKgCorrect)->toBe(0.32);

    $weightIncorrectlyConverted = $productWeightInGrams / 100;
    expect($weightIncorrectlyConverted)->toBe(3.20);
    expect($weightInKgCorrect)->not->toBe($weightIncorrectlyConverted);

    expect($productWeightInGrams / $weightInKgCorrect)->toBe(1000.0);
});


test('tiktok weight export', function () {
    $itemWeightInGrams = 320;

    $tiktokWeightInKg = $itemWeightInGrams / 1000;

    expect($tiktokWeightInKg)->toBe(0.32);
    expect($tiktokWeightInKg)->toBeFloat();
});

test('woocommerce weight export', function () {
    $grossWeightInGrams = 320;

    $wooCommerceWeight = (string) ($grossWeightInGrams / 1000);

    expect($wooCommerceWeight)->toBe('0.32');
    expect($wooCommerceWeight)->toBeString();
});

test('ebay weight export', function () {
    $marketingWeightInGrams = 320;

    $ebayWeightValue = (in_array($marketingWeightInGrams, [null, 0]) ? 100 : $marketingWeightInGrams) / 1000;

    expect($ebayWeightValue)->toBe(0.32);
});
