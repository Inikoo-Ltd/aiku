<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 21 Jul 2025 08:28:03 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Fulfilment\StoredItem;
use Exception;
use Illuminate\Console\Command;
use Sentry;

class StoreShopifyLocationToProductVariant extends RetinaAction
{
    use WithActionUpdate;


    /** level 1: upload includes price
     *  level 0: match excludes price
     */
    public function handle(Portfolio $portfolio): array
    {
        $customerSalesChannel = $portfolio->customerSalesChannel;

        /** @var ShopifyUser $shopifyUser */
        $shopifyUser = $customerSalesChannel->user;

        $client = $shopifyUser->getShopifyClient(true); // Get GraphQL client

        if (!$client) {
            Sentry::captureMessage("Failed to initialize Shopify GraphQL client");

            return [false, 'Failed to initialize Shopify GraphQL client'];
        }

        /** @var Product|StoredItem $product */
        $product = $portfolio->item;


        $productID = $portfolio->platform_product_id;


        if (!$productID) {
            Sentry::captureMessage("No Shopify product ID found in portfolio");

            return [false, 'No Shopify product ID found in portfolio'];
        }

        if (!CheckIfShopifyProductIDIsValid::run($productID)) {
            return [false, 'Invalid Shopify product ID'];
        }


        try {
            // Check if we have the variant ID
            if (!$portfolio->platform_product_variant_id) {
                $errorMessage = 'No Shopify variant ID found in portfolio';
                Sentry::captureMessage($errorMessage);
                return [false, $errorMessage];
            }

            // GraphQL mutation to activate inventory at location
            $mutation = <<<'MUTATION'
            mutation InventoryActivate($inventoryItemId: ID!, $locationId: ID!, $available: Int) {
              inventoryActivate(inventoryItemId: $inventoryItemId, locationId: $locationId, available: $available) {
                inventoryLevel {
                  id
                  quantities(names: ["available"]) {
                    name
                    quantity
                  }
                  item {
                    id
                  }
                  location {
                    id
                  }
                }
                userErrors {
                  field
                  message
                }
              }
            }
            MUTATION;

            $availableQuantity = $product->total_quantity;

            // Get inventory item ID from variant ID
            // Variant ID format: gid://shopify/ProductVariant/123
            // Inventory Item ID format: gid://shopify/InventoryItem/123
            // We need to query for the inventory item ID first
            $variantQuery = <<<'QUERY'
            query GetVariantInventoryItem($id: ID!) {
              productVariant(id: $id) {
                inventoryItem {
                  id
                }
              }
            }
            QUERY;

            $variantResponse = $client->request($variantQuery, ['id' => $portfolio->platform_product_variant_id]);

            if (!empty($variantResponse['errors']) || !isset($variantResponse['body'])) {
                $errorMessage = 'Error getting inventory item ID: '.json_encode($variantResponse['errors'] ?? []);
                Sentry::captureMessage($errorMessage);
                return [false, $errorMessage];
            }

            $variantBody = $variantResponse['body']->toArray();
            $inventoryItemId = $variantBody['data']['productVariant']['inventoryItem']['id'] ?? null;

            if (!$inventoryItemId) {
                $errorMessage = 'Could not get inventory item ID from variant';
                Sentry::captureMessage($errorMessage);
                return [false, $errorMessage];
            }

            // Prepare variables for the mutation
            $variables = [
                'inventoryItemId' => $inventoryItemId,
                'locationId' => $shopifyUser->shopify_location_id,
                'available' => (int) $availableQuantity ?? 0
            ];

            // Make the GraphQL request
            $response = $client->request($mutation, $variables);

            if (!empty($response['errors']) || !isset($response['body'])) {
                $errorMessage = 'Error in API response: '.json_encode($response['errors'] ?? []);
                UpdatePortfolio::run($portfolio, [
                    'errors_response' => [$errorMessage]
                ]);
                Sentry::captureMessage("Inventory activation failed A: ".$errorMessage);

                return [false, $errorMessage];
            }

            $body = $response['body']->toArray();

            if (!empty($body['data']['inventoryActivate']['userErrors'])) {
                $errors = $body['data']['inventoryActivate']['userErrors'];
                $errorMessage = 'User errors: '.json_encode($errors);

                UpdatePortfolio::run($portfolio, [
                    'errors_response' => [$errorMessage]
                ]);
                Sentry::captureMessage("Inventory activation failed B: ".$errorMessage);

                return [false, $errorMessage];
            }

            SaveShopifyProductData::run($portfolio);

            return [true, ''];
        } catch (Exception $e) {
            Sentry::captureException($e);
            UpdatePortfolio::run($portfolio, [
                'errors_response' => [$e->getMessage()]
            ]);

            return [false, $e->getMessage()];
        }
    }

    public function getCommandSignature(): string
    {
        return 'shopify:update_location_product_variant {portfolio_id}';
    }


    public function asCommand(Command $command): void
    {
        $portfolio = Portfolio::find($command->argument('portfolio_id'));

        if (!$portfolio) {
            $command->error("Portfolio not found");

            return;
        }

        list($status, $result) = $this->handle($portfolio);
        if ($status) {
            $command->info("\nInventory location and quantity updated successfully");
            print_r($result);
        }
    }
}
