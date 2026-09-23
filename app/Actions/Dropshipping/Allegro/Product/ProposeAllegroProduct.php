<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Mar 2025 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Allegro\Product;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\AllegroUser;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class ProposeAllegroProduct
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    private const array ATTRIBUTE_UNITS = [
        'weight' => 'g',
        'width'  => 'm',
        'height' => 'm',
        'depth'  => 'm',
    ];

    /**
     * Propose a new product to the Allegro catalogue.
     * POST /sale/products
     *
     * Returns the proposed product data including its ID, which is then
     * used when creating the offer via POST /sale/product-offers.
     */
    public function handle(AllegroUser $allegroUser, Portfolio $portfolio, $attributes = []): array
    {
        /** @var Product $product */
        $product = $portfolio->item;

        $productImages = [];
        foreach ($product->images as $image) {
            $image = UploadProductImageToAllegro::run($allegroUser, $image);
            $productImages[] = [
                'url' => Arr::get($image, 'location')
            ];
        }

        $description = $allegroUser->sanitizeAllegroDescription($portfolio->customer_description);

        $productData = [
            'name'     => Str::substr($portfolio->customer_product_name, 0, 75),
            'category' => [
                'id' => Arr::get($attributes, 'category_id')
            ],
            'images'     => $productImages,
            'parameters' => $this->buildParameters($portfolio, Arr::get($attributes, 'parameters', [])),
            'language' => Arr::get($attributes, 'language', 'en-US'),
            'description' => [
                'sections' => [
                    [
                        'items' => [
                            [
                                'type'    => 'TEXT',
                                'content' => $description
                            ]
                        ]
                    ]
                ]
            ]
        ];

        return $allegroUser->proposeProduct($productData);
    }

    private function buildParameters(Portfolio $portfolio, array $categoryParameters): array
    {
        /** @var Product $product */
        $product = $portfolio->item;

        $parameters = [];
        $matchedValueIds = [];

        $productAttributeMap = $this->getProductAttributeMap($portfolio);
        foreach (Arr::get($categoryParameters, 'parameters', []) as $param) {
            $paramId       = $param['id'];
            $paramName     = strtolower($param['name'] ?? '');
            $paramType     = $param['type'] ?? 'STRING'; // STRING | INTEGER | FLOAT | DICTIONARY
            $isRequired    = $param['required'] ?? false;
            $restrictions  = $param['restrictions'] ?? [];

            if (!$isRequired) {
                continue;
            }

            // 1. Always add EAN/GTIN if available
            if (str_contains($paramName, 'ean') || str_contains($paramName, 'gtin') || $paramId === '225694') {
                if ($product->barcode) {
                    $parameters[] = [
                        'id'     => $paramId,
                        'values' => [(string) $product->barcode]
                    ];
                }
                continue;
            }

            $attribute = $this->resolveProductAttribute($paramName) ?? '';
            $value     = $productAttributeMap[$attribute] ?? null;

            $entry = ['id' => $paramId];

            switch (Str::upper($paramType)) {
                case 'DICTIONARY':
                    $dictValues = collect($param['dictionary'] ?? [])
                        ->filter(
                            fn ($d) =>
                            empty($d['dependsOnValueIds']) ||
                            !empty(array_intersect($d['dependsOnValueIds'], $matchedValueIds))
                        )
                        ->values()
                        ->toArray();

                    $ambiguousValueId    = Arr::get($param, 'options.ambiguousValueId');
                    $customValuesEnabled = Arr::get($param, 'options.customValuesEnabled', false);

                    $matchedDictId = $this->matchDictionaryValue($value, $dictValues)
                        ?? Arr::get($dictValues, '0.id');

                    if (!$matchedDictId) {
                        continue 2;
                    }

                    $entry['valuesIds'] = [$matchedDictId];

                    if ($customValuesEnabled) {
                        $entry['values'] = [$value];
                    }

                    if ($ambiguousValueId) {
                        $entry['ambiguousValueId'] = $ambiguousValueId;
                    }

                    $matchedValueIds[] = $matchedDictId;
                    break;
                case 'INTEGER':
                case 'FLOAT':
                    if (!is_numeric($value)) {
                        continue 2;
                    }

                    $numericValue = (float) $value;
                    $allegroUnit  = Arr::get($param, 'unit');
                    if ($allegroUnit && isset(self::ATTRIBUTE_UNITS[$attribute])) {
                        $numericValue = convertUnits($numericValue, self::ATTRIBUTE_UNITS[$attribute], $allegroUnit === 'kg' ? 'Kg' : $allegroUnit);
                    }

                    $precision       = Str::upper($paramType) === 'INTEGER' ? 0 : Arr::get($restrictions, 'precision', 3);
                    $entry['values'] = [(string) round($numericValue, $precision)];
                    break;

                case 'STRING':
                default:

                    if (blank($value)) {
                        continue 2;
                    }

                    $maxLength = Arr::get($restrictions, 'maxLength', 255);
                    $entry['values'] = [Str::substr((string) $value, 0, $maxLength)];
                    break;
            }

            $parameters[] = $entry;
        }

        return $parameters;
    }

    private function getProductAttributeMap(Portfolio $portfolio): array
    {
        /** @var Product $product */
        $product = $portfolio->item;

        return [
            'name'        => $portfolio->customer_product_name ?? null,
            'brand'       => 'Ancient Wisdom',
            'type'        => $product->family?->name ?? null,
            'color'       => $product->color ?? null,
            'size'        => $product->size ?? null,
            'weight'      => max((int) $product->gross_weight, (int) $product->marketing_weight) ?: null,
            'width'       => Arr::get($product->marketing_dimensions, 'w') ?: null,
            'height'      => Arr::get($product->marketing_dimensions, 'h') ?: null,
            'depth'       => Arr::get($product->marketing_dimensions, 'l') ?: null,
            'material'    => 'Mix',
            'model'       => $product->family?->name ?? null,
            'mpn'         => $product->mpn ?? null,         // Manufacturer Part Number
            'sku'         => $product->code ?? null,
            'description' => $product->description ?? null,
            'condition'   => 'NEW',
            'capacity'    => 10,
            'essential_oil_type' => $product->essential_oil_type ?? 'OIL',
            'ean'         => $product->barcode ?? null,
            'manufacturer_code' => $product->code ?? null,
            'tariff_code' => $product->tariff_code ?? null,
        ];
    }

    private function resolveProductAttribute(string $paramName): ?string
    {
        $keywordMap = [
            'brand' => ['brand', 'manufacturer', 'marka', 'producent', 'manufacturer\'s scent name'],
            'manufacturer_code' => ['Manufacturer code', 'manufacturer code', 'kod producenta'],
            'type'      => ['type', 'rodzaj', 'typ', 'kind'],
            'size'     => ['size', 'rozmiar'],
            'weight'   => ['weight', 'waga', 'masa'],
            'width'    => ['width', 'szerokosc', 'szerokość'],
            'height'   => ['height', 'wysokosc', 'wysokość'],
            'depth'    => ['depth', 'glebokosc', 'głębokość', 'length', 'dlugosc', 'długość'],
            'material'   => ['material', 'materiał', 'skład', 'sklad', 'composition', 'ingredients'],
            'model'     => ['model', 'nazwa handlowa', 'trade name'],
            'mpn'      => ['mpn', 'part number', 'numer katalogowy'],
            'ean' => ['ean (gtin)', 'ean', 'gtin', 'gtin code', 'gtin/ean', 'ean/gtin', 'ean/gtin code'],
            'sku'      => ['sku', 'code', 'reference'],
            'condition' => ['condition', 'stan'],
            'description' => ['opis', 'opis produktu'],
            'capacity' => ['capacity', 'capacity (ml)'],
            'essential_oil_type' => ['essential oil type'],
            'tariff_code' => ['tariff code', 'customs tariff code'],
            'name' => ['name', 'nazwa'],
        ];

        foreach ($keywordMap as $attribute => $keywords) {
            if (array_any($keywords, fn ($keyword) => str_contains($paramName, $keyword))) {
                return $attribute;
            }
        }

        return null;
    }

    private function matchDictionaryValue(mixed $value, array $dictValues): ?string
    {
        $normalizedValue = strtolower(trim((string) $value));

        foreach ($dictValues as $dictEntry) {
            if (strtolower(trim($dictEntry['value'] ?? '')) === $normalizedValue) {
                return (string) $dictEntry['id'];
            }
        }

        foreach ($dictValues as $dictEntry) {
            if (str_contains(strtolower($dictEntry['value'] ?? ''), $normalizedValue)) {
                return (string) $dictEntry['id'];
            }
        }

        return null;
    }
}
