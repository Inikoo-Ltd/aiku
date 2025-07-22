<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 18 Feb 2025 10:56:59 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Fulfilment\Callback;

use App\Actions\Dropshipping\Shopify\Fulfilment\Webhooks\CreateFulfilmentOrderFromShopify;
use App\Actions\Dropshipping\Shopify\WithShopifyApi;
use App\Actions\OrgAction;
use App\Models\Dropshipping\ShopifyUser;


class AcceptShopifyFulfillmentRequest extends OrgAction
{

    use WithShopifyApi;

    /**
     * @throws \Throwable
     */
    public function handle(ShopifyUser $shopifyUser, array $fulfillmentOrderData): array
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

            list($status, $res) = $this->doPost($shopifyUser, $mutation, $variables);
            if (!$status) {
                return $res;
            }
            $body = $res['body']->toArray();

            // Check for user errors
            $userErrors = $body['data']['fulfillmentOrderAcceptFulfillmentRequest']['userErrors'] ?? [];
            if (!empty($userErrors)) {
                return [false, 'User errors: '.json_encode($userErrors)];
            }

            // Get the fulfillment order status
            $fulfillmentOrder = $body['data']['fulfillmentOrderAcceptFulfillmentRequest']['fulfillmentOrder'] ?? null;

            if ($fulfillmentOrder) {
                CreateFulfilmentOrderFromShopify::run($shopifyUser, $fulfillmentOrderData);

                return [true, 'Fulfillment order accepted'];
            }

            return [false, 'No fulfillment order data in response'];
        } catch (\Exception $e) {
            return [false, 'Exception occurred: '.$e->getMessage()];
        }
    }
}
