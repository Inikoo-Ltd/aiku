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
     * @throws \Throwable
     */
    public function handle(ShopifyUser $shopifyUser, string $orderID, string $message): void
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

            $this->doPost($shopifyUser, $mutation, $variables);

        } catch (\Exception $e) {
            Sentry::captureException($e);
        }
    }
}
