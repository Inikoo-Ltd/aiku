<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 Oct 2026 13:00:00 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\Dropshipping\Shopify\WithShopifyApi;
use App\Helpers\NaturalLanguage;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

/**
 * Shopify has no dimensions, ingredients or origin fields, so the specifications our product pages show
 * travel as product metafields in the "aw" namespace. Their definitions are created in the store so the
 * theme editor offers them as dynamic sources.
 */
trait WithShopifyProductSpecifications
{
    use WithShopifyApi;

    /**
     * @return array<string, array{name: string, type: string}>
     */
    public static function specificationDefinitions(): array
    {
        return [
            'net_weight'        => ['name' => 'Net weight', 'type' => 'weight'],
            'shipping_weight'   => ['name' => 'Shipping weight', 'type' => 'weight'],
            'dimensions'        => ['name' => 'Dimensions', 'type' => 'single_line_text_field'],
            'ingredients'       => ['name' => 'Materials/Ingredients', 'type' => 'multi_line_text_field'],
            'country_of_origin' => ['name' => 'Origin country', 'type' => 'single_line_text_field'],
            'cpnp'              => ['name' => 'CPNP', 'type' => 'single_line_text_field'],
        ];
    }

    /**
     * @return array<int, array{namespace: string, key: string, type: string, value: string}>
     */
    public function specificationMetafields(Product $product): array
    {
        $countries = array_filter(array_map(
            fn (string $country) => Arr::get(NaturalLanguage::make()->country(trim($country)), 'name'),
            explode(',', $product->country_of_origin ?? '')
        ));

        $values = [
            'net_weight'        => $product->marketing_weight ? json_encode(['value' => $product->marketing_weight, 'unit' => 'g']) : null,
            'shipping_weight'   => $product->gross_weight ? json_encode(['value' => $product->gross_weight, 'unit' => 'g']) : null,
            'dimensions'        => NaturalLanguage::make()->dimensions($product->marketing_dimensions),
            'ingredients'       => strip_tags((string)$product->marketing_ingredients),
            'country_of_origin' => implode(', ', $countries),
            'cpnp'              => $product->cpnp_number,
        ];

        $metafields = [];
        foreach (self::specificationDefinitions() as $key => $definition) {
            if (blank($values[$key])) {
                continue;
            }

            $metafields[] = [
                'namespace' => 'aw',
                'key'       => $key,
                'type'      => $definition['type'],
                'value'     => (string)$values[$key],
            ];
        }

        return $metafields;
    }

    public function ensureSpecificationDefinitions(ShopifyUser $shopifyUser): void
    {
        $cacheKey = 'shopify-aw-specification-definitions:'.$shopifyUser->id;

        if (Cache::has($cacheKey)) {
            return;
        }

        $mutation = <<<'MUTATION'
            mutation metafieldDefinitionCreate($definition: MetafieldDefinitionInput!) {
                metafieldDefinitionCreate(definition: $definition) {
                    createdDefinition {
                        id
                    }
                    userErrors {
                        code
                        message
                    }
                }
            }
        MUTATION;

        $allDefined = true;
        foreach (self::specificationDefinitions() as $key => $definition) {
            [$status, $res] = $this->doPost($shopifyUser, $mutation, [
                'definition' => [
                    'name'      => $definition['name'],
                    'namespace' => 'aw',
                    'key'       => $key,
                    'type'      => $definition['type'],
                    'ownerType' => 'PRODUCT',
                ],
            ]);

            $userErrors = $status ? Arr::get($res['body']->toArray(), 'data.metafieldDefinitionCreate.userErrors', []) : [];
            $taken      = collect($userErrors)->every(fn (array $error) => Arr::get($error, 'code') === 'TAKEN');

            if (!$status || !$taken) {
                $allDefined = false;
            }
        }

        if ($allDefined) {
            Cache::forever($cacheKey, true);
        }
    }
}
