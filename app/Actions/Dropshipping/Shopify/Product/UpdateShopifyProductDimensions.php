<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 08:26:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Shopify stores only a shipping weight on the variant; the rest of the specifications go as metafields.
 */
class UpdateShopifyProductDimensions
{
    use AsAction;
    use WithShopifyProductSpecifications;

    public function handle(CustomerSalesChannel $customerSalesChannel, Portfolio $portfolio): array
    {
        if ($portfolio->isShopifyVariantAdopted()) {
            return [false, 'This portfolio is linked to a variant the merchant already had, its data is never changed'];
        }

        /** @var ShopifyUser $shopifyUser */
        $shopifyUser = $customerSalesChannel->user;

        /** @var Product $product */
        $product = $portfolio->item;

        $this->ensureSpecificationDefinitions($shopifyUser);

        $metafields = array_map(
            fn (array $metafield) => array_merge($metafield, ['ownerId' => $portfolio->platform_product_id]),
            $this->specificationMetafields($product)
        );

        $variant        = ['id' => $portfolio->platform_product_variant_id];
        $shippingWeight = $product->gross_weight ?: $product->marketing_weight;
        if ($shippingWeight) {
            $variant['inventoryItem'] = [
                'measurement' => [
                    'weight' => [
                        'unit'  => 'GRAMS',
                        'value' => $shippingWeight,
                    ],
                ],
            ];
        }

        if (!$metafields && !isset($variant['inventoryItem'])) {
            return [true, ''];
        }

        $mutation = 'mutation updateSpecifications($productId: ID!, $variants: [ProductVariantsBulkInput!]!'.($metafields ? ', $metafields: [MetafieldsSetInput!]!' : '').') {
            productVariantsBulkUpdate(productId: $productId, variants: $variants) { userErrors { field message } }
            '.($metafields ? 'metafieldsSet(metafields: $metafields) { userErrors { field message } }' : '').'
        }';

        [$status, $res] = $this->doPost($shopifyUser, $mutation, array_filter([
            'productId'  => $portfolio->platform_product_id,
            'variants'   => [$variant],
            'metafields' => $metafields,
        ]));

        if (!$status) {
            return [false, $res];
        }

        $body       = $res['body']->toArray();
        $userErrors = array_merge(
            Arr::get($body, 'data.productVariantsBulkUpdate.userErrors', []),
            Arr::get($body, 'data.metafieldsSet.userErrors', []),
            Arr::get($body, 'errors', [])
        );

        if (!empty($userErrors)) {
            return [false, json_encode($userErrors)];
        }

        return [true, ''];
    }
}
