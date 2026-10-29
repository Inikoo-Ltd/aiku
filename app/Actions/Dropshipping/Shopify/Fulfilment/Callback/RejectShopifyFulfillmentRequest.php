<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 18 Feb 2025 10:56:59 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Fulfilment\Callback;

use App\Actions\Dropshipping\Shopify\WithShopifyApi;
use App\Actions\OrgAction;
use App\Models\Dropshipping\ShopifyUser;
use Sentry;

class RejectShopifyFulfillmentRequest extends OrgAction
{
    use WithShopifyApi;

    /**
     * Whether Shopify took the rejection; a refusal is reported to Sentry and never thrown.
     *
     * @throws \Throwable
     */
    public function handle(ShopifyUser $shopifyUser, string $orderID, string $message): bool
    {
        try {
            $mutation = <<<'MUTATION'
                    mutation rejectFulfillmentRequest($id: ID!, $message: String!) {
                            fulfillmentOrderRejectFulfillmentRequest(
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
                'id'      => $orderID,
                'message' => $message,
            ];

            [$status, $response] = $this->doPost($shopifyUser, $mutation, $variables);

            if (!$status) {
                return false;
            }

            return empty(data_get($response['body']->toArray(), 'data.fulfillmentOrderRejectFulfillmentRequest.userErrors'));
        } catch (\Exception $e) {
            Sentry::captureException($e);

            return false;
        }
    }
}
