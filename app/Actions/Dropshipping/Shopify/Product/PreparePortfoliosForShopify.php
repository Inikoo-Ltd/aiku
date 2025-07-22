<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 21 Jul 2025 20:04:47 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;

class PreparePortfoliosForShopify
{
    use AsAction;

    public function handle(CustomerSalesChannel $customerSalesChannel, int $fixLevel = 1): array
    {
        $portfoliosSynchronisation = [];
        /** @var ShopifyUser $shopifyUser */
        $shopifyUser = $customerSalesChannel->user;

        foreach ($customerSalesChannel->portfolios as $portfolio) {
            /** @var Product $product */
            $product = $portfolio->item;

            $hasValidProductId      = $this->isValidShopifyProductId($portfolio->platform_product_id);
            $productExistsInShopify = false;
            $hasVariantAtLocation   = false;
            if ($hasValidProductId) {
                $productExistsInShopify = $this->doesProductExistInShopify($shopifyUser, $portfolio->platform_product_id);
                $hasVariantAtLocation   = $this->hasVariantAtLocation($shopifyUser, $portfolio->platform_product_id);
            }


            $numberMatches = '';
            $matchesLabels = [];

            if (!$hasValidProductId || !$productExistsInShopify || !$hasVariantAtLocation) {
                $result = FindShopifyProductVariant::run($customerSalesChannel, trim($portfolio->sku.' '.$portfolio->barcode.' '.$product->code));

                $matches       = Arr::get($result, 'products', []);
                $numberMatches = count($matches);
                $matchesLabels = Arr::pluck($matches, 'title');
            }


            if ($fixLevel >= 1) {
                if ($hasValidProductId && !$hasVariantAtLocation) {
                    StoreShopifyProductVariant::run($portfolio);
                    $hasVariantAtLocation = $this->hasVariantAtLocation($shopifyUser, $portfolio->platform_product_id);
                }

                if (!$hasValidProductId || !$productExistsInShopify) {
                    StoreShopifyProduct::run($portfolio);
                    $hasVariantAtLocation = $this->hasVariantAtLocation($shopifyUser, $portfolio->platform_product_id);
                }
            }


            $portfoliosSynchronisation[$portfolio->id] = [
                'product_code'              => $product->code,
                'sku'                       => $portfolio->sku,
                'barcode'                   => $portfolio->barcode,
                'has_platform_product_id'   => $hasValidProductId,
                'product_exists_in_shopify' => $productExistsInShopify,
                'has_variant_at_location'   => $hasVariantAtLocation,
                'fix_level'                 => $fixLevel,
                'number_matches'            => $numberMatches,
                'matches_labels'            => $matchesLabels,
            ];
        }

        return $portfoliosSynchronisation;
    }

    /**
     * Check if a platform_product_id has a valid Shopify format
     *
     * @param  string|null  $platformProductId  The platform_product_id to validate
     *
     * @return bool True if the platform_product_id has a valid format, false otherwise
     */
    public static function isValidShopifyProductId(?string $platformProductId): bool
    {
        if (!$platformProductId) {
            return false;
        }

        // Valid format: gid://shopify/Product/{numeric_id}
        return (bool)preg_match('/^gid:\/\/shopify\/Product\/\d+$/', $platformProductId);
    }

    /**
     * Check if a product exists in Shopify
     *
     * @param  ShopifyUser  $shopifyUser  The Shopify user account to use for API access
     * @param  string  $productId  The Shopify product ID to check
     *
     * @return bool True if the product exists in Shopify, false otherwise
     */
    public static function doesProductExistInShopify(ShopifyUser $shopifyUser, string $productId): bool
    {
        $client = $shopifyUser->getShopifyClient(true); // Get GraphQL client

        if (!$client) {
            Sentry::captureMessage("Failed to initialize Shopify GraphQL client");

            return false;
        }

        try {
            // GraphQL query to check if a product exists
            $query = <<<'QUERY'
            query getProductExistence($id: ID!) {
              product(id: $id) {
                id
                title
              }
            }
            QUERY;

            // Prepare variables for the query
            $variables = [
                'id' => $productId
            ];

            // Make the GraphQL request
            $response = $client->request($query, $variables);

            if (!empty($response['errors']) || !isset($response['body'])) {
                $errorMessage = 'Error in API response: '.json_encode($response['errors'] ?? []);
                Sentry::captureMessage("Product existence check failed: ".$errorMessage);

                return false;
            }

            $body = $response['body']->toArray();

            // If the product exists, the response will contain product data
            return isset($body['data']['product']) && !empty($body['data']['product']);
        } catch (\Exception $e) {
            Sentry::captureException($e);

            return false;
        }
    }

    /**
     * Check if a Shopify product has a variant with inventory at a specific location
     *
     * @param  ShopifyUser  $shopifyUser  The Shopify user account to use for API access
     * @param  string  $productId  The Shopify product ID to check
     *
     * @return bool True if the product has a variant with inventory at the specified location, false otherwise
     */
    public static function hasVariantAtLocation(ShopifyUser $shopifyUser, string $productId): bool
    {
        if (!$shopifyUser->shopify_location_id) {
            Sentry::captureMessage("No location ID found for Shopify user");

            return false;
        }

        $client = $shopifyUser->getShopifyClient(true); // Get GraphQL client

        if (!$client) {
            Sentry::captureMessage("Failed to initialize Shopify GraphQL client");

            return false;
        }

        try {
            // GraphQL query to get product variants with inventory at the specified location
            $query = <<<'QUERY'
            query getProductInventoryAtLocation($productId: ID!, $locationId: ID!) {
              product(id: $productId) {
                variants(first: 50) {
                  edges {
                    node {
                      id
                      inventoryItem {
                        inventoryLevel(locationId: $locationId) {
                          id
                        }
                      }
                    }
                  }
                }
              }
            }
            QUERY;

            // Prepare variables for the query
            $variables = [
                'productId'  => $productId,
                'locationId' => $shopifyUser->shopify_location_id
            ];


            // Make the GraphQL request
            $response = $client->request($query, $variables);


            if (!empty($response['errors']) || !isset($response['body'])) {
                $errorMessage = 'Error in API response: '.json_encode($response['errors'] ?? []);
                Sentry::captureMessage("Product inventory check failed: ".$errorMessage);

                return false;
            }

            $body = $response['body']->toArray();

            // Check if product data exists in the response
            if (!isset($body['data']['product']) || !isset($body['data']['product']['variants']['edges'])) {
                Sentry::captureMessage("Product data not found in response");

                return false;
            }

            // Check if any variant has inventory at the specified location
            foreach ($body['data']['product']['variants']['edges'] as $edge) {
                $variant = $edge['node'];
                if (isset($variant['inventoryItem']['inventoryLevel'])
                    && isset($variant['inventoryItem']['inventoryLevel']['id'])
                    && $variant['inventoryItem']['inventoryLevel']['id']) {
                    return true;
                }
            }

            return false;
        } catch (\Exception $e) {
            Sentry::captureException($e);

            return false;
        }
    }


    public function getCommandSignature(): string
    {
        return 'shopify:prepare_portfolios_for_shopify {customerSalesChannel} {--fix_level=0 : Fix level (1, 2, or 3)}';
    }

    public function asCommand(Command $command): void
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->firstOrFail();
        $fixLevel             = (int)$command->option('fix_level');

        // Validate fix_level
        if ($fixLevel < 0 || $fixLevel > 3) {
            $command->error("Invalid fix level: $fixLevel. Fix level must be 1, 2, or 3.");

            return;
        }

        $portfoliosSynchronisation = $this->handle($customerSalesChannel, $fixLevel);

        if (empty($portfoliosSynchronisation)) {
            $command->info("No portfolios found for synchronization.");

            return;
        }

        $tableData = [];
        $counter   = 1;

        foreach ($portfoliosSynchronisation as $portfolioId => $portfolio) {
            $tableData[] = [
                'counter'        => $counter,
                'id'             => $portfolioId,
                'product_code'   => $portfolio['product_code'] ?? 'N/A',
                'sku'            => $portfolio['sku'] ?? 'N/A',
                'barcode'        => $portfolio['barcode'] ?? 'N/A',
                'valid_id'       => $portfolio['has_platform_product_id'] ? 'Yes' : 'No',
                'exists'         => $portfolio['product_exists_in_shopify'] ? 'Yes' : 'No',
                'at_location'    => $portfolio['has_variant_at_location'] ? 'Yes' : 'No',
                'number_matches' => $portfolio['number_matches'] ?? 0,
                'matches_labels' => implode(', ', $portfolio['matches_labels'] ?? [])
            ];
            $counter++;
        }

        // Output results in table format
        $this->table(
            ['#', 'ID', 'Product Code', 'SKU', 'Barcode', 'Valid Product ID', 'Exists in Shopify', 'At Location', 'Matches', 'Match Labels'],
            $tableData,
            $command
        );

        // Summary
        $totalPortfolios    = count($portfoliosSynchronisation);
        $validProductIds    = count(array_filter($portfoliosSynchronisation, function ($portfolio) {
            return $portfolio['has_platform_product_id'] ?? false;
        }));
        $existsInShopify    = count(array_filter($portfoliosSynchronisation, function ($portfolio) {
            return $portfolio['product_exists_in_shopify'] ?? false;
        }));
        $variantsAtLocation = count(array_filter($portfoliosSynchronisation, function ($portfolio) {
            return $portfolio['has_variant_at_location'] ?? false;
        }));


        $command->info("\nResults:");
        $command->info("- $validProductIds out of $totalPortfolios portfolios have valid Shopify product IDs");
        $command->info("- $existsInShopify out of $totalPortfolios portfolios exist in Shopify");
        $command->info("- $variantsAtLocation out of $totalPortfolios portfolios have variants at the specified location");
    }

    /**
     * Display a table in the console.
     */
    protected function table(array $headers, array $rows, Command $command): void
    {
        $command->table($headers, $rows);
    }

}
