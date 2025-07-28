<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 26 Jul 2025 23:10:11 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Fulfilment\Callback;

use App\Actions\Dropshipping\Shopify\WithShopifyApi;
use App\Actions\OrgAction;
use App\Models\Dropshipping\ShopifyUser;
use Sentry;

class RejectShopifyCancellationRequest extends OrgAction
{
    use WithShopifyApi;

    /**
     * @throws \Throwable
     */
    public function handle(ShopifyUser $shopifyUser, string $orderID, string $message): void
    {
        try {
            $mutation = <<<'MUTATION'
                    mutation rejectCancellationRequest($id: ID!, $message: String!) {
                            fulfillmentOrderRejectCancellationRequest(
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
