<?php

namespace App\Actions\Dropshipping\Shopify\Fulfilment\Callback;

use App\Actions\Dropshipping\Shopify\WithShopifyApi;
use App\Actions\OrgAction;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Support\Arr;
use Sentry;

class SplitShopifyFulfillmentRequest extends OrgAction
{
    use WithShopifyApi;

    /**
     * @throws \Throwable
     */
    public function handle(
        ShopifyUser $shopifyUser,
        array $fulfillmentOrder
    ): array {
        try {
            $lineItems = $fulfillmentOrder['lineItems']['edges'];

            $fulfillmentOrderItemsDefined = [];
            foreach ($lineItems as $fulfillmentOrderItems) {
                $lineItem = $fulfillmentOrderItems['node'];
                $productId = Arr::get($lineItem, 'lineItem.product.id');

                /** @var Portfolio $portfolio */
                $portfolio = $shopifyUser->customerSalesChannel->portfolios()
                    ->where('platform_product_id', $productId)->exists();

                if ($portfolio) {
                    $fulfillmentOrderItemsDefined[] = [
                        'id' => $lineItem['id'],
                        'quantity' => $lineItem['remainingQuantity']
                    ];
                }
            }

            $destination = isset($fulfillmentOrder['destination']);

            if (count($fulfillmentOrderItemsDefined) === count($lineItems) && $destination) {
                return $fulfillmentOrder;
            }

            $rejectMsg = __("The items can't be fulfilled because you don't have the items in your portfolio.");

            if (! $destination) {
                $rejectMsg = __("Order don't have shipping information");
            }

            RejectShopifyFulfillmentRequest::run($shopifyUser, $fulfillmentOrder['id'], $rejectMsg);

            if (! $destination) {
                return ['error' => $rejectMsg];
            }

            $fulfillmentOrderSplits = [
                [
                    'fulfillmentOrderId' => $fulfillmentOrder['id'],
                    'fulfillmentOrderLineItems' => $fulfillmentOrderItemsDefined
                ]
            ];

            $mutation = <<<'MUTATION'
            mutation fulfillmentOrderSplit(
                $fulfillmentOrderSplits: [FulfillmentOrderSplitInput!]!
            ) {
                fulfillmentOrderSplit(
                    fulfillmentOrderSplits: $fulfillmentOrderSplits
                ) {
                    fulfillmentOrderSplits {
                        fulfillmentOrder {
                            id
                            lineItems(first: 10) {
                                edges {
                                    cursor
                                    node {
                                        id
                                        totalQuantity
                                    }
                                }
                            }
                        }
                        remainingFulfillmentOrder {
                            id
                            lineItems(first: 10) {
                                edges {
                                    cursor
                                    node {
                                        id
                                        totalQuantity
                                    }
                                }
                            }
                        }
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }
            MUTATION;

            $variables = [
                'fulfillmentOrderSplits' => $fulfillmentOrderSplits
            ];

            list($status, $response) = $this->doPost($shopifyUser, $mutation, $variables);

            $body = $response['body']->toArray();

            $remainingFulfillmentOrder = Arr::get($body, 'data.fulfillmentOrderSplit.fulfillmentOrderSplits.0.remainingFulfillmentOrder');

            if (!Arr::has($remainingFulfillmentOrder, 'id')) {
                return [];
            }

            ResubmitShopifyFulfillmentRequest::run($shopifyUser, $remainingFulfillmentOrder['id']);

            return [];
        } catch (\Exception $e) {
            Sentry::captureException($e);

            return ['error' => $e->getMessage()];
        }
    }
}
