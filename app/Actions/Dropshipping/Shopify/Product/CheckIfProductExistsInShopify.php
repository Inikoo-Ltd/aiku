<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 08:07:29 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Models\Dropshipping\ShopifyUser;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;

class CheckIfProductExistsInShopify
{
    use AsAction;

    /**
     * Check if a product exists in Shopify
     *
     * @param  ShopifyUser  $shopifyUser  The Shopify user account to use for API access
     * @param  string  $productId  The Shopify product ID to check
     *
     * @return bool True if the product exists in Shopify, false otherwise
     */
    public function handle(ShopifyUser $shopifyUser, ?string $productId): bool
    {
        if (!$productId) {
            return false;
        }

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
}
