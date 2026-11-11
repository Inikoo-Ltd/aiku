<?php

/*
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Catalogue\Variant;

use App\Models\Catalogue\Product;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsObject;

class GetVariantProductSizeLabel
{
    use AsObject;

    private const string SIZE_PATTERN = '/^(\d+(?:\.\d+)?)\s*(ml|l|litre|litres|g|kg)$/i';

    /**
     * @return array{label: string, litres: ?float, kilograms: ?float, is_sample: bool, resolved: bool}
     */
    public function handle(?string $optionLabel, string $productCode, ?string $unit, float|string|null $units): array
    {
        $optionLabel = trim((string)$optionLabel);
        $isSample    = in_array(strtolower($optionLabel), ['tester', 'sample'], true);
        $size        = $this->resolveSize($optionLabel, $productCode, $unit, $units);

        if ($size) {
            return [
                'label'     => $isSample ? __(':size sample', ['size' => $size['label']]) : $size['label'],
                'litres'    => $this->perLitreAllowed($size, $unit, $units) ? $size['litres'] : null,
                'kilograms' => $this->isSingleContainer($size['kilograms'], $units) ? $size['kilograms'] : null,
                'is_sample' => $isSample,
                'resolved'  => true,
            ];
        }

        return [
            'label'     => $isSample ? __('Sample') : ($optionLabel !== '' ? $optionLabel : $productCode),
            'litres'    => null,
            'kilograms' => null,
            'is_sample' => $isSample,
            'resolved'  => false,
        ];
    }

    public function forBasketLine(?string $optionLabel, ?string $productCode, ?string $unit, float|string|null $units): ?string
    {
        if ($optionLabel === null || $optionLabel === '' || !$productCode) {
            return null;
        }

        $size = $this->handle($optionLabel, $productCode, $unit, $units);

        return $size['resolved'] ? $size['label'] : null;
    }

    public function forProduct(Product $product): ?string
    {
        $variant = $product->variant_id ? $product->variant : null;

        if (!$variant) {
            return null;
        }

        $optionLabel = data_get($variant->data, 'products.'.$product->id.'.'.data_get($variant->data, 'variants.0.label'));

        return $this->forBasketLine($optionLabel, $product->code, $product->unit, $product->units);
    }

    /**
     * @return array{label: string, litres: ?float, kilograms: ?float}|null
     */
    private function resolveSize(string $optionLabel, string $productCode, ?string $unit, float|string|null $units): ?array
    {
        $isSample = in_array(strtolower($optionLabel), ['tester', 'sample'], true);
        $size     = $this->parse($optionLabel);

        if (!$size && ($optionLabel === '' || $isSample || strtolower($optionLabel) === 'bulk')) {
            $size = $this->parse(Str::afterLast($productCode, '-')) ?? $this->parseUnits($unit, $units);
        }

        $sizeFromUnits = $this->parseUnits($unit, $units);
        if ($isSample && $size && $sizeFromUnits && $sizeFromUnits['label'] !== $size['label']) {
            return null;
        }

        return $size;
    }

    /**
     * Sizes of a variant group for a product card, smallest first; null unless every option is a size.
     *
     * @param array<int, array{code?: ?string, unit?: ?string, units?: float|string|null, option?: ?string}>|string|null $options
     *
     * @return array<int, string>|null
     */
    public function variantSizes(array|string|null $options): ?array
    {
        $options = is_string($options) ? json_decode($options, true) : $options;

        if (!is_array($options) || count($options) < 2) {
            return null;
        }

        $sizes = [];
        foreach ($options as $option) {
            $optionLabel = trim((string)Arr::get($option, 'option'));
            $code        = (string)Arr::get($option, 'code', '');
            $size        = $this->handle($optionLabel, $code, Arr::get($option, 'unit'), Arr::get($option, 'units'));

            if (!$size['resolved']) {
                return null;
            }

            $rawSize = $this->resolveSize($optionLabel, $code, Arr::get($option, 'unit'), Arr::get($option, 'units'));

            $sizes[] = [
                'label'  => $size['label'],
                'sample' => $size['is_sample'],
                'amount' => $rawSize['litres'] ?? $rawSize['kilograms'] ?? 0,
            ];
        }

        usort($sizes, fn (array $a, array $b) => [$b['sample'], $a['amount']] <=> [$a['sample'], $b['amount']]);

        return array_values(array_unique(array_column($sizes, 'label')));
    }

    /**
     * @return array{label: string, litres: ?float, kilograms: ?float}|null
     */
    private function parse(string $token): ?array
    {
        if (!preg_match(self::SIZE_PATTERN, trim($token), $matches)) {
            return null;
        }

        $amount = (float)$matches[1];
        $unit   = strtolower($matches[2]);

        if ($unit === 'g' || $unit === 'kg') {
            $grams = $unit === 'kg' ? $amount * 1000 : $amount;

            return [
                'label'     => $grams >= 1000 ? $this->number($grams / 1000).'kg' : $this->number($grams).'g',
                'litres'    => null,
                'kilograms' => round($grams / 1000, 6),
            ];
        }

        $millilitres = $unit === 'ml' ? $amount : $amount * 1000;

        return [
            'label'     => $millilitres >= 1000 ? $this->number($millilitres / 1000).'L' : $this->number($millilitres).'ml',
            'litres'    => round($millilitres / 1000, 6),
            'kilograms' => null,
        ];
    }

    /**
     * @return array{label: string, litres: ?float, kilograms: ?float}|null
     */
    private function parseUnits(?string $unit, float|string|null $units): ?array
    {
        $unit = strtolower(trim((string)$unit));

        if (!in_array($unit, ['litre', 'l', 'kg'], true) || !is_numeric($units) || (float)$units <= 0) {
            return null;
        }

        return $this->parse($this->number((float)$units).$unit);
    }

    /**
     * @param array{label: string, litres: ?float, kilograms: ?float} $size
     */
    private function perLitreAllowed(array $size, ?string $unit, float|string|null $units): bool
    {
        if (strtolower(trim((string)$unit)) === 'kg') {
            return false;
        }

        return !(is_numeric($units) && (float)$units > 1 && abs((float)$units - (float)$size['litres']) > 0.000001);
    }

    private function isSingleContainer(?float $amount, float|string|null $units): bool
    {
        if ($amount === null) {
            return false;
        }

        return !(is_numeric($units) && (float)$units > 1 && abs((float)$units - $amount) > 0.000001);
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }
}
