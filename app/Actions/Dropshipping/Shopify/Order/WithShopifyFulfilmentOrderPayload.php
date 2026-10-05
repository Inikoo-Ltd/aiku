<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 02 Sept 2026 15:24:07 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Order;

use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Shopify orders normally reach AW through the fulfillment_order_notification webhook, so
 * CreateFulfilmentOrderFromShopify expects a fulfilment order rather than an order. Both the
 * single-order retry and the bulk fetch rebuild that same payload from here, so the two cannot
 * drift apart from each other or from the webhook.
 */
trait WithShopifyFulfilmentOrderPayload
{
    /**
     * Without our location nothing can be told apart as ours, so Shopify is not asked at all.
     *
     * @throws \Exception
     */
    protected function orderWithFulfilmentOrdersFields(ShopifyUser $shopifyUser): string
    {
        if (blank($shopifyUser->shopify_location_id)) {
            throw new \Exception(__('The channel has no AW location in Shopify yet, reset the channel before retrying.'));
        }

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
            fulfillmentOrders(first: 10) {
                edges {
                    node {
                        id
                        status
                        requestStatus
                        assignedLocation {
                            location {
                                id
                                name
                            }
                        }
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
                            pageInfo {
                                hasNextPage
                            }
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
     * An order can hold fulfilment orders for the merchant's own locations, other fulfilment services,
     * requests we rejected or ones Shopify recreated after a move; only those assigned to our location
     * and requested from us are ours. Every one of them is returned, open first: a request missed by
     * the webhook is still open, one already accepted is in progress, and one order can hold both.
     * A fulfilment order with more lines than were read is left to the webhook, since accepting it
     * would promise Shopify lines the AW order never gets.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildFulfilmentOrderPayloads(ShopifyUser $shopifyUser, array $order): array
    {
        $rawFulfilmentOrders = collect(data_get($order, 'fulfillmentOrders.edges', []))->pluck('node')->filter();

        $this->alertIfAssignedToAnotherAikuLocation($shopifyUser, $order, $rawFulfilmentOrders);

        [$tooLong, $fulfilmentOrders] = $rawFulfilmentOrders
            ->filter(fn ($fulfilmentOrder) => data_get($fulfilmentOrder, 'assignedLocation.location.id') === $shopifyUser->shopify_location_id
                && in_array(data_get($fulfilmentOrder, 'requestStatus'), ['SUBMITTED', 'ACCEPTED'], true)
                && in_array(data_get($fulfilmentOrder, 'status'), ['OPEN', 'IN_PROGRESS'], true)
                && data_get($fulfilmentOrder, 'destination'))
            ->partition(fn ($fulfilmentOrder) => data_get($fulfilmentOrder, 'lineItems.pageInfo.hasNextPage'));

        foreach ($tooLong as $fulfilmentOrder) {
            Log::warning('Shopify fulfilment order '.$fulfilmentOrder['id'].' of customer sales channel '.$shopifyUser->customer_sales_channel_id.' has more lines than the order fetch reads, left to the fulfilment request webhook.');
        }

        return $fulfilmentOrders
            ->sortBy(fn ($fulfilmentOrder) => $fulfilmentOrder['status'] === 'OPEN' ? 0 : 1)
            ->map(fn ($fulfilmentOrder) => array_merge($fulfilmentOrder, [
                'order' => Arr::only($order, ['id', 'name', 'createdAt', 'processedAt', 'customer']),
            ]))
            ->values()
            ->all();
    }

    /**
     * A fulfilment request assigned to an aiku location that is not the current one is never seen
     * by the webhook, since its callback points at whatever shopify_user created that location
     * (often one we long since soft-deleted): the order is otherwise lost silently until a customer
     * notices it missing. This is the only place that still sees it, so it is where it gets flagged.
     */
    private function alertIfAssignedToAnotherAikuLocation(ShopifyUser $shopifyUser, array $order, Collection $fulfilmentOrders): void
    {
        foreach ($fulfilmentOrders as $fulfilmentOrder) {
            $location = data_get($fulfilmentOrder, 'assignedLocation.location');

            if (!$location
                || $location['id'] === $shopifyUser->shopify_location_id
                || !str_starts_with((string) data_get($location, 'name'), 'aiku-')
                || !in_array(data_get($fulfilmentOrder, 'status'), ['OPEN', 'IN_PROGRESS'], true)) {
                continue;
            }

            $message = sprintf(
                'Shopify order %s (%s) has a fulfilment order %s assigned to a non-current aiku location %s (%s) of shopify_user %d; requestStatus %s.',
                data_get($order, 'id'),
                data_get($order, 'name'),
                data_get($fulfilmentOrder, 'id'),
                $location['id'],
                data_get($location, 'name'),
                $shopifyUser->id,
                data_get($fulfilmentOrder, 'requestStatus')
            );

            Log::warning($message);
            \Sentry::captureMessage($message);
        }
    }
}
