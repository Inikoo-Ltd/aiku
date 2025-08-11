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

class DeleteShopifyVariant
{
    use AsAction;

    public function handle(Portfolio $portfolio): bool
    {
        $productID = $portfolio->platform_product_id;
        $variantID = $portfolio->platform_product_variant_id;


        if (!$productID || !$variantID) {
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
            // GraphQL mutation to delete the variant
            $mutation = <<<'MUTATION'
            mutation productVariantsBulkDelete($productId: ID!, $variantsIds: [ID!]!) {
              productVariantsBulkDelete(productId: $productId, variantsIds: $variantsIds) {
                product {
                     id
                     title
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
                'productId' => $productID,
                'variantsIds' => [$variantID]
            ];

            // Make the GraphQL request for the mutation
            $response = $client->request($mutation, $variables);

            if (!empty($response['errors']) || !isset($response['body'])) {
                $errorMessage = 'Error in API response: '.json_encode($response['errors'] ?? []);
                Sentry::captureMessage("Variant deletion failed: ".$errorMessage);
                return false;
            }

            $body = $response['body']->toArray();

            // Check for user errors in the response
            if (!empty($body['data']['productVariantsBulkDelete']['userErrors'])) {
                $errors = $body['data']['productVariantsBulkDelete']['userErrors'];
                $errorMessage = 'User errors: '.json_encode($errors);
                Sentry::captureMessage("Variant deletion failed: ".$errorMessage);
                return false;
            }

            // Check if the variant was actually deleted
            $deletedIds = $body['data']['productVariantsBulkDelete']['deletedVariantIds'] ?? [];
            if (!in_array($variantID, $deletedIds)) {
                Sentry::captureMessage("Variant not found in deleted IDs list");
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
