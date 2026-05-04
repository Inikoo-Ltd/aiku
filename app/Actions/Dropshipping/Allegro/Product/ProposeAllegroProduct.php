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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class ProposeAllegroProduct
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

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

        $productData = [
            'name'     => Str::substr($portfolio->customer_product_name, 0, 75),
            'category' => [
                'id' => Arr::get($attributes, 'category_id')
            ],
            'images'     => $productImages,
            'parameters' => $this->buildParameters($portfolio, Arr::get($attributes, 'parameters', [])),
            'description' => [
                'sections' => [
                    [
                        'items' => [
                            [
                                'type'    => 'TEXT',
                                'content' => $allegroUser->sanitizeAllegroDescription($portfolio->customer_description)
                            ]
                        ]
                    ]
                ]
            ],
            'language' => 'en-US'
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

            $value = $this->resolveProductValue($paramName, $productAttributeMap);

            if ($value === null) {
                if ($isRequired) {
                    \Log::warning("Required Allegro parameter not mapped", [
                        'param_id'   => $paramId,
                        'param_name' => $param['name'],
                        'product_id' => $product->id,
                    ]);
                }
                continue;
            }

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

                    Log::info('Dictionary values: ' . $ambiguousValueId . '-' . $customValuesEnabled . '-' . $param['name'] . '-' . $matchedDictId);


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
                    $numericValue = is_numeric($value) ? $value : null;
                    if ($numericValue === null) {
                        continue 2;
                    }
                    $entry['values'] = [(string) $numericValue];

                    if (!empty($restrictions['allowedUnits'])) {
                        $entry['unit'] = $restrictions['allowedUnits'][0];
                    }
                    break;

                case 'STRING':
                default:
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

        $w = max(Arr::get($product->marketing_dimensions, 'w', 1), 20);
        $h = max(Arr::get($product->marketing_dimensions, 'h', 1), 20);
        $l = max(Arr::get($product->marketing_dimensions, 'l', 1), 80);

        return [
            'name'        => $portfolio->customer_product_name ?? null,
            'brand'       => 'Ancient Wisdom' ?? null,
            'type'        => $product->family?->name ?? null,
            'color'       => $product->color ?? null,
            'size'        => $product->size ?? null,
            'weight'      => $w,
            'width'       => $l,
            'height'      => $h,
            'depth'       => $product->depth ?? null,
            'material'    => 'Mix',
            'model'       => $product->family?->name ?? null,
            'mpn'         => $product->mpn ?? null,         // Manufacturer Part Number
            'sku'         => $product->code ?? null,
            'description' => $product->description ?? null,
            'condition'   => 'NEW',
        ];
    }

    private function resolveProductValue(string $paramName, array $attributeMap): mixed
    {
        $keywordMap = [
            'brand' => ['brand', 'manufacturer', 'marka', 'producent'],
            'type'      => ['type', 'rodzaj', 'typ', 'kind'],
            'size'     => ['size', 'rozmiar'],
            'weight'   => ['weight', 'waga', 'masa'],
            'width'    => ['width', 'szerokosc', 'szerokość'],
            'height'   => ['height', 'wysokosc', 'wysokość'],
            'depth'    => ['depth', 'glebokosc', 'głębokość', 'length', 'dlugosc'],
            'material'   => ['material', 'materiał', 'skład', 'sklad', 'composition', 'ingredients'],
            'model'     => ['model', 'nazwa handlowa', 'trade name'],
            'mpn'      => ['mpn', 'part number', 'numer katalogowy'],
            'sku'      => ['sku', 'code', 'reference'],
            'condition' => ['stan'],
        ];

        foreach ($keywordMap as $attribute => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($paramName, $keyword)) {
                    return $attributeMap[$attribute] ?? null;
                }
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
