<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 08:10:53 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Models\Dropshipping\ShopifyUser;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;

class CheckIfProductHasVariantAtLocation
{
    use AsAction;

    public function handle(ShopifyUser $shopifyUser, ?string $productId): bool
    {
        if (!$productId) {
            return false;
        }

        if (!CheckIfShopifyProductIDIsValid::run($productId)) {
            return false;
        }


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
                Sentry::captureMessage("Product data not found in response A");

                return false;
            }

            // Check if any variant has inventory at the specified location
            foreach ($body['data']['product']['variants']['edges'] as $edge) {
                $variant = $edge['node'];
                if (isset($variant['inventoryItem']['inventoryLevel']['id']) && $variant['inventoryItem']['inventoryLevel']['id']) {
                    return true;
                }
            }

            return false;
        } catch (\Exception $e) {
            Sentry::captureException($e);

            return false;
        }
    }
}
