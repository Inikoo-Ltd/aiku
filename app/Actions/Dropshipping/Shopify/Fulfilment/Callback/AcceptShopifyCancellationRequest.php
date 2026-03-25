<?php

/*
 * author Louis Perez
 * created on 25-03-2026-08h-50m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Dropshipping\Shopify\Fulfilment\Callback;

use App\Actions\Dropshipping\Shopify\WithShopifyApi;
use App\Actions\OrgAction;
use App\Models\Dropshipping\ShopifyUser;
use Sentry;

class AcceptShopifyCancellationRequest extends OrgAction
{
    use WithShopifyApi;

    /**
     * @throws \Throwable
     */
    public function handle(ShopifyUser $shopifyUser, string $orderID, string $message): void
    {
        try {
            $mutation = <<<'MUTATION'
                    mutation acceptCancellationRequest($id: ID!, $message: String!) {
                        fulfillmentOrderAcceptCancellationRequest(
                            id: $id, 
                            message: $message
                        ) {
                            fulfillmentOrder {
                                id
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
                'id'      => $orderID,
                'message' => $message,
            ];

            $this->doPost($shopifyUser, $mutation, $variables);
        } catch (\Exception $e) {
            Sentry::captureException($e);
        }
    }
}
