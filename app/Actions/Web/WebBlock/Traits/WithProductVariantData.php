<?php

/*
 * author Louis Perez
 * created on 30-12-2025-14h-05m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\WebBlock\Traits;

use App\Http\Resources\Catalogue\ProductResourceForVariant;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Variant;

trait WithProductVariantData
{
    protected const SIBLINGS_VARIANT_LABEL = 'Siblings';

    /**
     * @return array{id: int|null, data: array, is_natural_variant: bool}|null
     */
    public function getProductVariantData(Product $product): ?array
    {
        if ($product->show_siblings_as_option) {
            return $this->getSiblingsAsVariantData($product);
        }

        if ($product->is_variant_leader) {
            return $this->getNaturalVariantData($product);
        }

        return null;
    }

    /**
     * @return array{id: null, data: array, is_natural_variant: false}|null
     */
    protected function getSiblingsAsVariantData(Product $product): ?array
    {
        if (!$product->family) {
            return null;
        }

        $siblings = $product->family->getActiveProducts()->sortBy('code')->values();

        return [
            'id'                 => null,
            'data'               => [
                'groupBy'  => self::SIBLINGS_VARIANT_LABEL,
                'products' => $siblings->mapWithKeys(fn (Product $sibling) => [
                    $sibling->id => [
                        self::SIBLINGS_VARIANT_LABEL => $sibling->code,
                        'product'                    => ProductResourceForVariant::make($sibling)->resolve(),
                        'is_leader'                  => $sibling->id == $product->id,
                    ]
                ])->all(),
                'variants' => [
                    [
                        'label'   => self::SIBLINGS_VARIANT_LABEL,
                        'options' => $siblings->pluck('code')->all(),
                    ]
                ],
            ],
            'is_natural_variant' => false,
        ];
    }

    /**
     * @return array{id: int, data: array, is_natural_variant: true}|null
     */
    protected function getNaturalVariantData(Product $product): ?array
    {
        $variant = Variant::where('leader_id', $product->id)->first();

        if (!$variant) {
            return null;
        }

        return [
            'id'                 => $variant->id,
            'data'               => $variant->data,
            'is_natural_variant' => true,
        ];
    }

    protected function rejectHiddenVariantProducts(array $variant): array
    {
        $products = collect(data_get($variant, 'data.products'))
            ->reject(fn ($product) => data_get($product, 'is_hide', false))
            ->all();

        data_set($variant, 'data.products', $products);

        return $variant;
    }
}
