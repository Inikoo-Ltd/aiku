<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Jul 2025 20:09:06 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\ShopifyUser;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;

class DeleteShopifyProduct
{
    use AsAction;

    public function handle(Portfolio $portfolio): bool
    {
        $productID = $portfolio->platform_product_id;

        if (!$productID) {
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
            // GraphQL mutation to delete the product
            $mutation = <<<'MUTATION'
            mutation productDelete($input: ProductDeleteInput!) {
              productDelete(input: $input) {
                deletedProductId
                shop {
                  id
                }
                userErrors {
                  field
                  message
                }
              }
            }
            MUTATION;

            // Prepare variables for the mutation
            $variables = [
                'input' => [
                    'id' => $productID
                ]
            ];

            // Make the GraphQL request for the mutation
            $response = $client->request($mutation, $variables);

            if (!empty($response['errors']) || !isset($response['body'])) {
                $errorMessage = 'Error in API response: '.json_encode($response['errors'] ?? []);
                Sentry::captureMessage("Product deletion failed: ".$errorMessage);
                return false;
            }

            $body = $response['body']->toArray();

            // Check for user errors in the response
            if (!empty($body['data']['productDelete']['userErrors'])) {
                $errors = $body['data']['productDelete']['userErrors'];
                $errorMessage = 'User errors: '.json_encode($errors);
                Sentry::captureMessage("Product deletion failed: ".$errorMessage);
                return false;
            }

            // Check if the product was actually deleted
            $deletedProductId = $body['data']['productDelete']['deletedProductId'] ?? null;
            if (!$deletedProductId) {
                Sentry::captureMessage("No deleted product ID returned in response");
                return false;
            }

            // If we got here, the deletion was successful
            return true;
        } catch (\Exception $e) {
            Sentry::captureException($e);
            return false;
        }
    }
}
