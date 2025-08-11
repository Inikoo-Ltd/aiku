<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 19 Jul 2025 09:01:58 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\FulfilmentService;

use App\Actions\Dropshipping\Shopify\CheckShopifyChannel;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\ShopifyUser;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreFulfilmentService
{
    use AsAction;


    public function handle(CustomerSalesChannel $customerSalesChannel)
    {
        /** @var ShopifyUser $shopifyUser */
        $shopifyUser = $customerSalesChannel->user;


        if (!$shopifyUser) {
            return [false, 'No Shopify user found'];
        }

        $client = $shopifyUser->getShopifyClient();

        if (!$client) {
            return [false, 'Failed to initialize Shopify client'];
        }


        $fulfilmentServiceName = GetFulfilmentServiceName::run($customerSalesChannel);

        try {
            // GraphQL mutation to create a fulfillment service
            $mutation = <<<'MUTATION'
            mutation fulfillmentServiceCreate($name: String!, $callbackUrl: URL!, $trackingSupport: Boolean!, $inventoryManagement: Boolean!) {
              fulfillmentServiceCreate(
                name: $name
                callbackUrl: $callbackUrl
                trackingSupport: $trackingSupport
                inventoryManagement: $inventoryManagement
              ) {
                fulfillmentService {
                  id
                  serviceName
                  callbackUrl
                  inventoryManagement
                  trackingSupport
                  fulfillmentOrdersOptIn
                }
                userErrors {
                  field
                  message
                }
              }
            }
            MUTATION;

            $variables = [
                'name'                => $fulfilmentServiceName,
                'callbackUrl'         => 'https://'.config('app.domain').'/webhooks/shopify/'.$shopifyUser->id,
                'trackingSupport'     => false,
                'inventoryManagement' => true,
            ];


            $response = $client->request('POST', '/admin/api/2025-07/graphql.json', [
                'json' => [
                    'query'     => $mutation,
                    'variables' => $variables
                ]
            ]);


            if (!empty($response['errors']) || !isset($response['body'])) {
                return [false, 'Error in API response: '.json_encode($response['errors'] ?? [])];
            }

            $body = $response['body']->toArray();


            // Check for user errors in the response
            if (!empty($body['data']['fulfillmentServiceCreate']['userErrors'])) {
                $errors = $body['data']['fulfillmentServiceCreate']['userErrors'];

                return [false, 'User errors: '.json_encode($errors)];
            }

            // Return the created fulfillment service
            $fulfillmentService = $body['data']['fulfillmentServiceCreate']['fulfillmentService'] ?? null;

            if (!$fulfillmentService) {
                return [false, 'No fulfillment service data in response'];
            }

            CheckShopifyChannel::run($customerSalesChannel);
            UpdateFulfilmentServiceLocation::run($customerSalesChannel);
            $customerSalesChannel->update([
                'platform_status' => true
            ]);





            return [true, $fulfillmentService];
        } catch (\Exception $e) {
            \Sentry::captureMessage('Error creating fulfillment service: '.$e->getMessage());

            return [false, 'Exception: '.$e->getMessage()];
        }
    }
}
