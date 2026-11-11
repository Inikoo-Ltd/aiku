<?php

use App\Actions\Catalogue\Variant\GetVariantProductSizeLabel;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Variant;

test('product size label is read from the option, the code or the units', function (?string $option, string $code, ?string $unit, float|string|null $units, string $label, ?float $litres, bool $isSample, bool $resolved) {
    $size = GetVariantProductSizeLabel::run($option, $code, $unit, $units);
    unset($size['kilograms']);

    expect($size)->toBe([
        'label'     => $label,
        'litres'    => $litres,
        'is_sample' => $isSample,
        'resolved'  => $resolved,
    ]);
})->with([
    'tester from code'     => ['Tester', 'FOKG-264-10ml', 'Tester', '0.010', '10ml sample', 0.01, true, true],
    '50ml'                 => ['50ml', 'FOKG-264-50ml', 'Tester', '1', '50ml', 0.05, false, true],
    '5L'                   => ['5L', 'FOKG-02-5L', 'Litre', '1', '5L', 5.0, false, true],
    '0.5kg'                => ['0.5kg', 'EOKG-01-0.5kg', 'kg', '1', '500g', null, false, true],
    '0.5Kg mixed case'     => ['0.5Kg', 'EOKG-01', 'kg', '1', '500g', null, false, true],
    '250g'                 => ['250g', 'EOKG-02-250g', 'g', '1', '250g', null, false, true],
    '1kg'                  => ['1kg', 'EOKG-02-1kg', 'kg', '1', '1kg', null, false, true],
    'bulk litre'           => ['Bulk', 'FOKG-264', 'Litre', '1.000', '1L', 1.0, false, true],
    'bulk kg'              => ['Bulk', 'EOKG-01', 'kg', '1', '1kg', null, false, true],
    'bulk five litres'     => ['Bulk', 'FOKG-264', 'Litre', '5', '5L', 5.0, false, true],
    'tester unresolved'    => ['Tester', 'ABC-01', 'piece', '1', 'Sample', null, true, false],
    'ml size on a kg product has no litres' => ['50ml', 'FOKG-264-50ml', 'kg', '0.050', '50ml', null, false, true],
    'sample whose units contradict its code is unresolved' => ['Tester', 'RDEO5-01-10ml', 'Litre', '0.002', 'Sample', null, true, false],
    'bulk unresolved'      => ['Bulk', 'ABC-01', 'piece', '1', 'Bulk', null, false, false],
    '1000ml is 1L'         => ['1000ml', 'ABC-01', 'piece', '1', '1L', 1.0, false, true],
    '0.5L is 500ml'        => ['0.5L', 'ABC-01', 'piece', '1', '500ml', 0.5, false, true],
    '2.5L'                 => ['2.5L', 'ABC-01', 'piece', '1', '2.5L', 2.5, false, true],
    'other label keeps itself' => ['Large', 'X-2L', 'piece', '1', 'Large', null, false, false],
    'pack of six has no litres' => ['500ml', 'P-500ml', 'bottle', '6', '500ml', null, false, true],
    'five litre unit matches' => ['5L', 'F-5L', 'Litre', '5', '5L', 5.0, false, true],
    'empty label uses code' => [null, 'ABC-01', 'piece', '1', 'ABC-01', null, false, false],
]);

test('weight sizes carry their kilograms so the price per kg can be shown (HELP-4531)', function (?string $option, string $code, ?string $unit, float|string|null $units, ?float $kilograms) {
    expect(GetVariantProductSizeLabel::run($option, $code, $unit, $units)['kilograms'])->toBe($kilograms);
})->with([
    '0.5kg'               => ['0.5kg', 'EOKG-13-0.5kg', 'kg', '1', 0.5],
    'bulk kg'             => ['Bulk', 'EOKG-13', 'kg', '1', 1.0],
    '250g'                => ['250g', 'EOKG-02-250g', 'g', '1', 0.25],
    'litres have none'    => ['5L', 'FOKG-02-5L', 'Litre', '1', null],
    'unresolved has none' => ['Large', 'X-2L', 'piece', '1', null],
    'pack of six has none' => ['500g', 'P-500g', 'bag', '6', null],
]);

test('a variant card lists its sizes, samples first then smallest to largest (HELP-4610)', function () {
    $sizes = GetVariantProductSizeLabel::make()->variantSizes(json_encode([
        ['code' => 'FOKG-02-5L', 'unit' => 'Litre', 'units' => '1', 'option' => '5L'],
        ['code' => 'FOKG-02', 'unit' => 'Litre', 'units' => '1', 'option' => 'Bulk'],
        ['code' => 'FOKG-02-10ml', 'unit' => 'Litre', 'units' => '0.010', 'option' => 'Tester'],
        ['code' => 'FOKG-02-500ml', 'unit' => 'Litre', 'units' => '0.5', 'option' => '500ml'],
    ]));

    expect($sizes)->toBe(['10ml sample', '500ml', '1L', '5L']);
});

test('ml sizes stored in kg are still put in order on the card', function () {
    $sizes = GetVariantProductSizeLabel::make()->variantSizes([
        ['code' => 'FOKG-264-100ml', 'unit' => 'kg', 'units' => '0.1', 'option' => '100ml'],
        ['code' => 'FOKG-264-50ml', 'unit' => 'kg', 'units' => '0.05', 'option' => '50ml'],
    ]);

    expect($sizes)->toBe(['50ml', '100ml']);
});

test('a card shows no sizes unless every option is a size and there is more than one', function (array|string|null $options) {
    expect(GetVariantProductSizeLabel::make()->variantSizes($options))->toBeNull();
})->with([
    'plug variants' => [[
        ['code' => 'LAMP-UK', 'unit' => 'piece', 'units' => '1', 'option' => 'UK plug'],
        ['code' => 'LAMP-EU', 'unit' => 'piece', 'units' => '1', 'option' => 'EU plug'],
    ]],
    'one size only' => [[['code' => 'EOKG-13', 'unit' => 'kg', 'units' => '1', 'option' => '1kg']]],
    'not a variant' => [null],
]);

test('a variant product names its size for search results and order lines', function () {
    $variant = new Variant(['data' => [
        'variants' => [['label' => 'Size']],
        'products' => ['7' => ['Size' => 'Tester'], '8' => ['Size' => '1L']],
    ]]);

    $sample = (new Product())->forceFill(['id' => 7, 'variant_id' => 3, 'code' => 'FOKG-02-10ml', 'unit' => 'Litre', 'units' => '0.010']);
    $sample->setRelation('variant', $variant);

    $plain = (new Product())->forceFill(['id' => 9, 'variant_id' => null, 'code' => 'ABC-01', 'unit' => 'piece', 'units' => '1']);

    expect(GetVariantProductSizeLabel::make()->forProduct($sample))->toBe('10ml sample')
        ->and(GetVariantProductSizeLabel::make()->forProduct($plain))->toBeNull();
});
