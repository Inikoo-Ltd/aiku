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

class ReSubmitShopifyFulfillmentRequest extends OrgAction
{
    use WithShopifyApi;

    /**
     * @throws \Throwable
     */
    public function handle(ShopifyUser $shopifyUser, string $orderID): void
    {
        try {
            $mutation = <<<'MUTATION'
                    mutation fulfillmentOrderSubmitFulfillmentRequest($id: ID!) {
                            fulfillmentOrderSubmitFulfillmentRequest(
                                id: $id
                            ) {
                                originalFulfillmentOrder {
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
                'id'      => $orderID
            ];

            $this->doPost($shopifyUser, $mutation, $variables);

        } catch (\Exception $e) {
            Sentry::captureException($e);
        }
    }
}
