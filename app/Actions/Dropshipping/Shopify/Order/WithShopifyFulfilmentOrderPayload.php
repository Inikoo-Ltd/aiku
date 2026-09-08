<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 02 Sept 2026 15:24:07 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Order;

use Illuminate\Support\Arr;

/**
 * Shopify orders normally reach AW through the fulfillment_order_notification webhook, so
 * CreateFulfilmentOrderFromShopify expects a fulfilment order rather than an order. Both the
 * single-order retry and the bulk fetch rebuild that same payload from here, so the two cannot
 * drift apart from each other or from the webhook.
 */
trait WithShopifyFulfilmentOrderPayload
{
    protected function orderWithFulfilmentOrdersFields(): string
    {
        return <<<'FIELDS'
            id
            name
            createdAt
            processedAt
            customer {
                id
                email
                firstName
                lastName
                phone
            }
            fulfillmentOrders(first: 3) {
                edges {
                    node {
                        id
                        status
                        destination {
                            firstName
                            lastName
                            address1
                            address2
                            city
                            province
                            countryCode
                            zip
                            email
                            phone
                            company
                        }
                        lineItems(first: 30) {
                            edges {
                                node {
                                    id
                                    sku
                                    remainingQuantity
                                    lineItem {
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
                }
            }
        FIELDS;
    }

    /**
     * A fulfilment order already shipped or cancelled has nothing left to import, so the open one
     * is preferred and the others are only a fallback.
     */
    protected function buildFulfilmentOrderPayload(array $order): array
    {
        $fulfilmentOrders = collect(data_get($order, 'fulfillmentOrders.edges', []))
            ->pluck('node')
            ->filter();

        if ($fulfilmentOrders->isEmpty()) {
            return [];
        }

        $fulfilmentOrder = $fulfilmentOrders->firstWhere('status', 'OPEN')
            ?? $fulfilmentOrders->firstWhere('status', 'IN_PROGRESS')
            ?? $fulfilmentOrders->first();

        if (!Arr::get($fulfilmentOrder, 'destination')) {
            return [];
        }

        return array_merge($fulfilmentOrder, [
            'order' => Arr::only($order, ['id', 'name', 'createdAt', 'processedAt', 'customer']),
        ]);
    }
}
