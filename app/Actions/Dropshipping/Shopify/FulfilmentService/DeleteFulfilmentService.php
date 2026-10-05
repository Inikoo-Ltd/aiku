<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 19 Jul 2025 09:01:58 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\FulfilmentService;

use App\Models\Dropshipping\CustomerSalesChannel;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteFulfilmentService
{
    use AsAction;

    /**
     * $inventoryAction DELETE removes the location and its inventory levels with it: right for a
     * stale aiku location, whose quantities are only mirrors of our warehouse stock that the live
     * location already carries. Shopify refuses to transfer them to the new aiku location (the
     * destination must be merchant managed), and KEEP would hand the merchant a location with
     * phantom stock of our products. Null leaves Shopify's own default.
     */
    public function handle(CustomerSalesChannel $customerSalesChannel, string $fulfilmentServiceId, ?string $inventoryAction = null): array
    {
        $shopifyUser = $customerSalesChannel->user;
        if (!$shopifyUser) {
            return [false, 'No Shopify user provided'];
        }

        $client = $shopifyUser->getShopifyClient();

        if (!$client) {
            return [false, 'Failed to initialize Shopify client'];
        }

        try {
            // GraphQL mutation to delete a fulfillment service
            $mutation = <<<'MUTATION'
            mutation fulfillmentServiceDelete($id: ID!, $inventoryAction: FulfillmentServiceDeleteInventoryAction) {
              fulfillmentServiceDelete(id: $id, inventoryAction: $inventoryAction) {
                deletedId
                userErrors {
                  field
                  message
                }
              }
            }
            MUTATION;

            $variables = array_filter([
                'id'              => $fulfilmentServiceId,
                'inventoryAction' => $inventoryAction,
            ]);

            $response = $client->request('POST', '/admin/api/2025-07/graphql.json', [
                'json' => [
                    'query'     => $mutation,
                    'variables' => $variables
                ]
            ]);

            if (!empty($response['errors']) || !isset($response['body'])) {
                return [false, 'Error in API response: ' . json_encode($response['errors'] ?? [])];
            }

            $body = $response['body']->toArray();

            // Check for user errors in the response
            if (!empty($body['data']['fulfillmentServiceDelete']['userErrors'])) {
                $errors = $body['data']['fulfillmentServiceDelete']['userErrors'];
                return [false, 'User errors: ' . json_encode($errors)];
            }

            // Return the deleted fulfillment service ID
            $deletedId = $body['data']['fulfillmentServiceDelete']['deletedId'] ?? null;

            if (!$deletedId) {
                return [false, 'No deleted ID in response'];
            }

            return [true, ['id' => $deletedId]];
        } catch (\Exception $e) {
            \Sentry::captureMessage('Error deleting fulfillment service: ' . $e->getMessage());
            return [false, 'Exception: ' . $e->getMessage()];
        }
    }



}
