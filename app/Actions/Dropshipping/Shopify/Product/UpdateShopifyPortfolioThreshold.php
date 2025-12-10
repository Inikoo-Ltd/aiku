<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 08:26:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\Dropshipping\Portfolio\Logs\StorePlatformPortfolioLog;
use App\Actions\Dropshipping\Portfolio\Logs\UpdatePlatformPortfolioLog;
use App\Actions\Dropshipping\Shopify\WithShopifyApi;
use App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsStatusEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateShopifyPortfolioThreshold
{
    use AsAction;
    use WithShopifyApi;

    public function handle(CustomerSalesChannel $customerSalesChannel, Portfolio $portfolio): void
    {
        try {
            /** @var ShopifyUser $shopifyUser */
            $shopifyUser = $customerSalesChannel->user;

            $logs = [];
            $inventoryItems = [];

            $logs[] = StorePlatformPortfolioLog::run($portfolio, []);

            // Get variant ID (either from stored or fetch default variant)
            $variantId = Arr::get($portfolio->data, 'shopify_product.variants.edges.0.node.id');

            if (!$variantId) {
                // Fetch the default variant from the product
                $variantId = $this->getDefaultVariantId($shopifyUser, $portfolio->platform_product_id);

                if ($variantId) {
                    // Update the portfolio with the variant ID
                    $portfolio->update([
                        'platform_product_variant_id' => $variantId
                    ]);
                }
            }

            if (!$variantId) {
                return;
            }

            // Get inventory item ID from variant
            $inventoryItemId = $this->getInventoryItemId($shopifyUser, $variantId);

            if ($inventoryItemId) {
                $inventoryItems[] = [
                    'inventoryItemId' => $inventoryItemId,
                    'locationId' => $shopifyUser->shopify_location_id,
                    'quantity' => 0,
                ];
            }

            if (empty($inventoryItems)) {
                $this->bulkUpdateLogs($logs, [
                    'status' => PlatformPortfolioLogsStatusEnum::FAIL,
                    'response' => __('No valid inventory items found')
                ]);

                return;
            }

            $mutation = <<<'MUTATION'
                mutation inventorySetQuantities($input: InventorySetQuantitiesInput!) {
                    inventorySetQuantities(input: $input) {
                        inventoryAdjustmentGroup {
                            id
                            reason
                            changes {
                                name
                                delta
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
                'input' => [
                    'reason' => 'correction',
                    'name' => 'available',
                    'quantities' => $inventoryItems,
                    'ignoreCompareQuantity' => true
                ]
            ];

            list($status, $res) = $this->doPost($shopifyUser, $mutation, $variables);

            if (!$status) {
                $this->bulkUpdateLogs($logs, [
                    'status' => PlatformPortfolioLogsStatusEnum::FAIL,
                    'response' => $res
                ]);

                return;
            }

            $body = $res['body']->toArray();

            // Check for user errors
            $userErrors = $body['data']['inventorySetQuantities']['userErrors'] ?? [];
            if (!empty($userErrors)) {
                $this->bulkUpdateLogs($logs, [
                    'status' => PlatformPortfolioLogsStatusEnum::FAIL,
                    'response' => 'User errors: ' . json_encode($userErrors)
                ]);
                return;
            }

            $this->bulkUpdateLogs($logs, [
                'status' => PlatformPortfolioLogsStatusEnum::OK
            ]);

        } catch (\Throwable $e) {
            $this->bulkUpdateLogs($logs ?? [], [
                'status' => PlatformPortfolioLogsStatusEnum::FAIL,
                'response' => $e->getMessage()
            ]);
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

        $variables = [
            'id' => $productId
        ];

        list($status, $res) = $this->doPost($shopifyUser, $query, $variables);

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

        $variables = [
            'id' => $variantId
        ];

        list($status, $res) = $this->doPost($shopifyUser, $query, $variables);

        if (!$status) {
            return null;
        }

        $body = $res['body']->toArray();

        return $body['data']['productVariant']['inventoryItem']['id'] ?? null;
    }

    public function bulkUpdateLogs(array $platformPortfolioLogs, array $modelData): void
    {
        foreach ($platformPortfolioLogs as $platformPortfolioLog) {
            UpdatePlatformPortfolioLog::run($platformPortfolioLog, $modelData);
        }
    }
}
