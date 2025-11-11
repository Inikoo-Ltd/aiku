<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 08:07:29 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;

class CheckIfProductExistsInShopify
{
    use AsAction;


    public function handle(ShopifyUser $shopifyUser, ?string $productId): array
    {

        $result=[
            'exist'=>false,
            'error'=>false
        ];

        if (!$productId) {
            return $result;
        }

        if (!CheckIfShopifyProductIDIsValid::run($productId)) {
            return $result;
        }

        $client = $shopifyUser->getShopifyClient(true); // Get GraphQL client

        if (!$client) {
            Sentry::captureMessage("Failed to initialize Shopify GraphQL client");
            data_set($result, 'error', true);
            return $result;
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


            $variables = [
                'id' => $productId
            ];

            // Make the GraphQL request
            $response = $client->request($query, $variables);

            if (!empty($response['errors']) || !isset($response['body'])) {
                //$errorMessage = 'Error in API response: '.json_encode($response['errors'] ?? []);
                // Sentry::captureMessage("Product existence check failed: shopifyUser  $shopifyUser->id    >$productId<  V2 ".$errorMessage);
                data_set($result, 'error', true);
                return $result;

            }

            $body = $response['body']->toArray();

            // If the product exists, the response will contain product data

            if(Arr::has($body, 'data.product')){
                data_set($result, 'error', false);


                if(Arr::get($body, 'data.product.id')==$productId){
                    data_set($result, 'exist', true);
                }
            }

            return $result;



        } catch (\Exception $e) {
            // Sentry::captureException($e);

            return $result;
        }
    }
}
