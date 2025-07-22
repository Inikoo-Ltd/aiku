<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 18 Feb 2025 10:56:59 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Fulfilment\Callback;

use App\Actions\Dropshipping\Shopify\Fulfilment\Webhooks\CreateFulfilmentOrderFromShopify;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\ShopifyUser;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class AcceptFulfillmentRequest extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    public function handle(ShopifyUser $shopifyUser, array $fulfillmentOrderData)
    {
        try {
            $mutation = <<<'MUTATION'
mutation acceptFulfillmentRequest($id: ID!, $message: String!) {
  fulfillmentOrderAcceptFulfillmentRequest(
    id: $id
    message: $message
  ) {
    fulfillmentOrder {
      status
      requestStatus
    }
    userErrors {
      field
      message
    }
  }
}
MUTATION;

            $variables = [
                'id'      => $fulfillmentOrderData['id'],
                'message' => __('Your fulfillment request has been accepted.'),
            ];

            $client = $shopifyUser->getShopifyClient();
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

            // Check for user errors
            $userErrors = $body['data']['fulfillmentOrderAcceptFulfillmentRequest']['userErrors'] ?? [];
            if (!empty($userErrors)) {
                return [false, 'User errors: '.json_encode($userErrors)];
            }

            // Get the fulfillment order status
            $fulfillmentOrder = $body['data']['fulfillmentOrderAcceptFulfillmentRequest']['fulfillmentOrder'] ?? null;

            if ($fulfillmentOrder) {
                CreateFulfilmentOrderFromShopify::run($shopifyUser, $fulfillmentOrderData);
            }
        } catch (\Exception $e) {
            return [false, 'Exception occurred: ' . $e->getMessage()];
        }
    }
}
