<?php

namespace App\Actions\Dropshipping\Shopify\Fulfilment\Callback;

use App\Actions\Dropshipping\Shopify\WithShopifyApi;
use App\Actions\Dropshipping\Shopify\WithShopifyPortfolioMatching;
use App\Actions\OrgAction;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Support\Arr;
use Sentry;

class SplitShopifyFulfillmentRequest extends OrgAction
{
    use WithShopifyApi;
    use WithShopifyPortfolioMatching;

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
            $unmatchedLineItems           = [];
            foreach ($lineItems as $fulfillmentOrderItems) {
                $lineItem = $fulfillmentOrderItems['node'];

                $portfolio = $this->matchShopifyLineItemToPortfolio(
                    $shopifyUser->customerSalesChannel,
                    Arr::get($lineItem, 'lineItem.product.id'),
                    Arr::get($lineItem, 'lineItem.variant.id'),
                    Arr::get($lineItem, 'sku')
                );

                if (!$portfolio) {
                    $unmatchedLineItems[] = [
                        'sku'                => Arr::get($lineItem, 'sku'),
                        'product_id'         => Arr::get($lineItem, 'lineItem.product.id'),
                        'product_variant_id' => Arr::get($lineItem, 'lineItem.variant.id'),
                    ];

                    continue;
                }

                $fulfillmentOrderItemsDefined[] = [
                    'id' => $lineItem['id'],
                    'quantity' => $lineItem['remainingQuantity']
                ];
            }

            if ($unmatchedLineItems) {
                Sentry::captureMessage(
                    'Shopify fulfillment request has line items outside the portfolio of customer sales channel '
                    .$shopifyUser->customer_sales_channel_id.': '.json_encode($unmatchedLineItems)
                );
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

            if (count($fulfillmentOrderItemsDefined) === 0) {
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

            ReSubmitShopifyFulfillmentRequest::run($shopifyUser, $remainingFulfillmentOrder['id']);

            return [];
        } catch (\Exception $e) {
            Sentry::captureException($e);

            return ['error' => $e->getMessage()];
        }
    }
}
