<?php

use App\Actions\Catalogue\Variant\GetVariantProductSizeLabel;

test('product size label is read from the option, the code or the units', function (?string $option, string $code, ?string $unit, float|string|null $units, string $label, ?float $litres, bool $isSample, bool $resolved) {
    expect(GetVariantProductSizeLabel::run($option, $code, $unit, $units))->toBe([
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
