<?php

namespace App\Actions\Dropshipping\Shopify\Fulfilment;

use App\Actions\Dropshipping\Shopify\WithShopifyApi;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Ordering\Order;
use Illuminate\Validation\ValidationException;

class CancelFulfillOrderToShopify extends OrgAction
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
            mutation fulfillmentOrderCancel($id: ID!) {
              fulfillmentOrderCancel(id: $id) {
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

        if (!empty($response['body']['data']['fulfillmentOrderCancel']['userErrors'])) {
            throw ValidationException::withMessages([
                'messages' => collect($response['body']['data']['fulfillmentOrderCancel']['userErrors'])
                    ->pluck('message')
                    ->join(', ')
            ]);
        }
    }
}
