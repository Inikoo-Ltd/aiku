<?php

/*
 * author Louis Perez
 * created on 25-03-2026-11h-28m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Dropshipping\Shopify\Fulfilment;

use App\Actions\Dropshipping\Shopify\WithShopifyApi;
use App\Actions\Dropshipping\Shopify\Fulfilment\Callback\AcceptShopifyCancellationRequest;
use App\Actions\Dropshipping\Shopify\Fulfilment\Callback\RejectShopifyFulfillmentRequest;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
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

        $fulfilmentOrder = $this->readFulfilmentOrder($shopifyUser, $fulfillmentId);
        $status          = Arr::get($fulfilmentOrder, 'status');
        $requestStatus   = Arr::get($fulfilmentOrder, 'requestStatus');

        if ($requestStatus === 'CANCELLATION_REQUESTED') {
            if (!AcceptShopifyCancellationRequest::run($shopifyUser, $fulfillmentId, 'The order was cancelled')) {
                throw ValidationException::withMessages(['message' => 'Shopify refused to accept the cancellation request']);
            }
            $action = 'accept_cancellation';
        } elseif ($requestStatus === 'SUBMITTED') {
            if (!RejectShopifyFulfillmentRequest::run($shopifyUser, $fulfillmentId, 'The order was cancelled')) {
                throw ValidationException::withMessages(['message' => 'Shopify did not accept the rejection of the fulfilment request']);
            }
            $action = 'reject';
        } elseif ($status === 'IN_PROGRESS') {
            $this->closeFulfilmentOrder($shopifyUser, $fulfillmentId);
            $action = 'close';
        } else {
            $action = 'none';
        }

        $order->updateQuietly([
            'data->shopify_fulfilment_order' => [
                'status'         => $status,
                'request_status' => $requestStatus,
                'action'         => $action,
                'at'             => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     * @throws \Illuminate\Validation\ValidationException
     */
    private function readFulfilmentOrder(ShopifyUser $shopifyUser, string $fulfillmentId): array
    {
        $query = <<<'QUERY'
            query fulfillmentOrderState($id: ID!) {
                fulfillmentOrder(id: $id) {
                    id
                    status
                    requestStatus
                }
            }
        QUERY;

        try {
            [$status, $response] = $this->doPost($shopifyUser, $query, ['id' => $fulfillmentId]);
        } catch (\Exception $e) {
            throw ValidationException::withMessages(['message' => $e->getMessage()]);
        }

        if (!$status) {
            throw ValidationException::withMessages(['message' => $response]);
        }

        $fulfilmentOrder = Arr::get($response['body']->toArray(), 'data.fulfillmentOrder');
        if (empty($fulfilmentOrder)) {
            throw ValidationException::withMessages(['message' => 'Shopify could not find the fulfilment order '.$fulfillmentId]);
        }

        return $fulfilmentOrder;
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    private function closeFulfilmentOrder(ShopifyUser $shopifyUser, string $fulfillmentId): void
    {
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

        $userErrors = Arr::get($response['body']->toArray(), 'data.fulfillmentOrderClose.userErrors', []);
        if (!empty($userErrors)) {
            throw ValidationException::withMessages([
                'messages' => collect($userErrors)
                    ->pluck('message')
                    ->join(', ')
            ]);
        }
    }
}
