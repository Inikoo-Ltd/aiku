<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 08:26:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\Dropshipping\Shopify\WithShopifyApi;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Catalogue\Product;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;

class UpdateShopifyProductDimensions
{
    use AsAction;
    use WithShopifyApi;

    public function handle(CustomerSalesChannel $customerSalesChannel, Portfolio $portfolio): void
    {
        try {
            /** @var ShopifyUser $shopifyUser */
            $shopifyUser = $customerSalesChannel->user;
            $variantId = $portfolio->platform_product_variant_id;

            if (!$variantId) {
                $variantId = $this->getDefaultVariantId($shopifyUser, $portfolio->platform_product_id);

                if ($variantId) {
                    $portfolio->update([
                        'platform_product_variant_id' => $variantId
                    ]);
                }
            }

            $inventoryItemId = $this->getInventoryItemId($shopifyUser, $variantId);

            if (! $inventoryItemId) {
                return;
            }

            /** @var Product $product */
            $product = $portfolio->item;

            $width  = $product->width  ?? null;
            $length = $product->length ?? null;
            $height = $product->height ?? null;

            if ($width === null && $length === null && $height === null) {
                return;
            }

            $measurementInput = [];

            if ($width !== null) {
                $measurementInput['width'] = [
                    'value' => (float) $width,
                    'unit'  => 'CENTIMETERS',
                ];
            }

            if ($length !== null) {
                $measurementInput['length'] = [
                    'value' => (float) $length,
                    'unit'  => 'CENTIMETERS',
                ];
            }

            if ($height !== null) {
                $measurementInput['height'] = [
                    'value' => (float) $height,
                    'unit'  => 'CENTIMETERS',
                ];
            }

            $mutation = <<<'MUTATION'
                mutation inventoryItemUpdate($id: ID!, $input: InventoryItemInput!) {
                    inventoryItemUpdate(id: $id, input: $input) {
                        inventoryItem {
                            id
                            measurement {
                                weight {
                                    value
                                    unit
                                }
                                dimensions {
                                    width {
                                        value
                                        unit
                                    }
                                    length {
                                        value
                                        unit
                                    }
                                    height {
                                        value
                                        unit
                                    }
                                }
                            }
                        }
                        userErrors {
                            field
                            message
                        }
                    }
                }
            MUTATION;

            $variables = [
                'id'    => $inventoryItemId,
                'input' => [
                    'measurement' => [
                        'dimensions' => $measurementInput,
                    ],
                ],
            ];

            list($status, $res) = $this->doPost($shopifyUser, $mutation, $variables);

            if (!$status) {
                return;
            }

            $body = $res['body']->toArray();

            $userErrors = $body['data']['inventoryItemUpdate']['userErrors'] ?? [];

            if (!empty($userErrors)) {
                return;
            }
        } catch (\Throwable $e) {
            Sentry::captureException($e);
        }
    }

    private function getDefaultVariantId(ShopifyUser $shopifyUser, string $productId): ?string
    {
        $query = <<<'QUERY'
            query getProductVariants($id: ID!) {
                product(id: $id) {
                    id
                    variants(first: 1) {
                        edges {
                            node {
                                id
                            }
                        }
                    }
                }
            }
        QUERY;

        list($status, $res) = $this->doPost($shopifyUser, $query, ['id' => $productId]);

        if (!$status) {
            return null;
        }

        $body = $res['body']->toArray();

        return $body['data']['product']['variants']['edges'][0]['node']['id'] ?? null;
    }

    private function getInventoryItemId(ShopifyUser $shopifyUser, string $variantId): ?string
    {
        $query = <<<'QUERY'
            query getInventoryItemId($id: ID!) {
                productVariant(id: $id) {
                    id
                    inventoryItem {
                        id
                    }
                }
            }
        QUERY;

        list($status, $res) = $this->doPost($shopifyUser, $query, ['id' => $variantId]);

        if (!$status) {
            return null;
        }

        $body = $res['body']->toArray();

        return $body['data']['productVariant']['inventoryItem']['id'] ?? null;
    }
}
