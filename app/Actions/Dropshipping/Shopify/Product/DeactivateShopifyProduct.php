<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Jul 2025 14:23:07 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\ShopifyUser;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;

class DeactivateShopifyProduct
{
    use AsAction;

    public function handle(Portfolio $portfolio): bool
    {
        $productID = $portfolio->platform_product_id;
        $locationID = $portfolio->customerSalesChannel->user->shopify_location_id;


        if (!$productID || !$locationID) {
            return false;
        }

        /** @var ShopifyUser $shopifyUser */
        $shopifyUser = $portfolio->customerSalesChannel->user;
        $client = $shopifyUser->getShopifyClient(true); // Get GraphQL client

        if (!$client) {
            Sentry::captureMessage("Failed to initialize Shopify GraphQL client");
            return false;
        }

        try {
            // GraphQL query to get inventoryLevel.id using variant ID and location ID
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
                'productId'  => $productID,
                'locationId' => $locationID
            ];

            // Make the GraphQL request
            $response = $client->request($query, $variables);

            if (!empty($response['errors']) || !isset($response['body'])) {
                $errorMessage = 'Error in API response: '.json_encode($response['errors'] ?? []);
                Sentry::captureMessage("Inventory level ID retrieval failed: ".$errorMessage);
                return false;
            }

            $body = $response['body']->toArray();

            // Check if the inventoryLevel.id exists in the response
            if (isset($body['data']['product']['variants']['edges'][0]['node']['inventoryItem']['inventoryLevel']['id'])) {
                $variantLevelId = $body['data']['product']['variants']['edges'][0]['node']['inventoryItem']['inventoryLevel']['id'];

                // Now use the inventoryLevel.id to deactivate the inventory
                $mutation = <<<'MUTATION'
                mutation inventoryDeactivate($inventoryLevelId: ID!) {
                  inventoryDeactivate(inventoryLevelId: $inventoryLevelId) {
                    userErrors {
                      field
                      message
                    }
                  }
                }
                MUTATION;

                // Prepare variables for the mutation
                $mutationVariables = [
                    'inventoryLevelId' => $variantLevelId
                ];

                // Make the GraphQL request for the mutation
                $mutationResponse = $client->request($mutation, $mutationVariables);

                dd($mutationVariables, $mutationResponse);

                if (!empty($mutationResponse['errors']) || !isset($mutationResponse['body'])) {
                    $errorMessage = 'Error in API response: '.json_encode($mutationResponse['errors'] ?? []);
                    Sentry::captureMessage("Inventory deactivation failed: ".$errorMessage);
                    return false;
                }

                $mutationBody = $mutationResponse['body']->toArray();

                // Check for user errors in the response
                if (!empty($mutationBody['data']['inventoryDeactivate']['userErrors'])) {
                    $errors = $mutationBody['data']['inventoryDeactivate']['userErrors'];
                    $errorMessage = 'User errors: '.json_encode($errors);
                    Sentry::captureMessage("Inventory deactivation failed: ".$errorMessage);
                    return false;
                }

                // If we got here, the deactivation was successful
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Sentry::captureException($e);
            return false;
        }
    }

}
