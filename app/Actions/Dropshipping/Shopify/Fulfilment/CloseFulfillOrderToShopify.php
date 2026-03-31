<?php

/*
 * author Louis Perez
 * created on 25-03-2026-11h-28m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Dropshipping\Shopify\Fulfilment;

use App\Actions\Dropshipping\Shopify\WithShopifyApi;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Ordering\Order;
use Illuminate\Validation\ValidationException;

class CloseFulfillOrderToShopify extends OrgAction
{
    use WithShopifyApi;
    use WithActionUpdate;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(Order $order): void
    {
        $fulfillmentId = $order->platform_order_id;

        /** @var ShopifyUser $shopifyUser */
        $shopifyUser = $order->customerSalesChannel->user;

        if (!$shopifyUser || !$fulfillmentId) {
            return;
        }

        $mutation = <<<'MUTATION'
            mutation fulfillmentOrderClose($id: ID!, $message: String) {
                fulfillmentOrderClose(id: $id, message: $message) {
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
            'id' => $fulfillmentId
        ];

        try {
            list($status, $response) = $this->doPost($shopifyUser, $mutation, $variables);
        } catch (\Exception $e) {

            throw ValidationException::withMessages(['message' => $e->getMessage()]);
        }

        if (!$status) {
            throw ValidationException::withMessages(['message' => $response]);
        }

        if (!empty($response['errors'])) {

            throw ValidationException::withMessages([
                'messages' => collect($response['errors'])
                    ->pluck('message')
                    ->join(', ')
            ]);
        }

        if (!empty($response['body']['data']['fulfillmentOrderClose']['userErrors'])) {
            throw ValidationException::withMessages([
                'messages' => collect($response['body']['data']['fulfillmentOrderClose']['userErrors'])
                    ->pluck('message')
                    ->join(', ')
            ]);
        }

    }
}
