<?php

/*
 * author Louis Perez
 * created on 25-03-2026-14h-24m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Dropshipping\Shopify\Fulfilment;

use App\Actions\Dropshipping\Shopify\WithShopifyApi;
use App\Actions\OrgAction;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class CheckOrderDetailFromShopify extends OrgAction
{
    use WithShopifyApi;

    public function handle(Order $order): void
    {
        $orderId = data_get($order->data, 'shopify_data.order.id', '');

        /** @var ShopifyUser $shopifyUser */
        $shopifyUser = $order->customerSalesChannel->user;

        if (!$shopifyUser || !$orderId) {
            return;
        }

        $mutation = <<<'MUTATION'
            query getOrder($id: ID!) {
                order(id: $id) {
                    id
                    name
                    createdAt
                    fulfillable
                    displayFulfillmentStatus
                    lineItems(first: 100) {
                        edges {
                            node {
                                id
                                name
                                sku
                                quantity
                                nonFulfillableQuantity
                                variant {
                                    id
                                }
                                product {
                                    id
                                }
                            }
                        }
                    }
                    fulfillments(first: 10) {
                        name
                        status
                        createdAt
                        fulfillmentLineItems(first: 10) {
                            edges {
                                node {
                                    id
                                    lineItem {
                                        name
                                        sku
                                        currentQuantity
                                        unfulfilledQuantity
                                        variant {
                                            id
                                        }
                                        product {
                                            id
                                        }
                                    }
                                }
                            }
                        }
                    }
                    nonFulfillableLineItems(first: 100) {
                        edges {
                            node {
                                id
                                sku
                                name
                                currentQuantity
                                nonFulfillableQuantity
                                product {
                                    id
                                }
                                variant {
                                    id
                                }
                            }
                        }
                    }
                }
            }
        MUTATION;

        $variables = [
            'id' => $orderId
        ];

        try {
            list($status, $response) = $this->doPost($shopifyUser, $mutation, $variables);
        } catch (\Exception $e) {
            throw ValidationException::withMessages(['message' => $e->getMessage()]);
        }

        $orderResponse = data_get($response['body']->toArray(), 'data.order', []);
        // Keep it this way. Command only used on local anyway
        dd($orderResponse);
    }


    public string $commandSignature = 'shopify:check_order_details {order}';

    public function asCommand(Command $command): void
    {
        $order = Order::where('slug', $command->argument('order'))->first();

        if ($order) {
            $this->handle($order);
        }
    }
}
