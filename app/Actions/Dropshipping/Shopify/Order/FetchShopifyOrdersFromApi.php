<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 02 Sept 2026 15:31:44 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Order;

use App\Actions\Dropshipping\Shopify\WithShopifyApi;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;

/**
 * Shopify orders reach AW through the fulfillment_order_notification webhook. An order is lost if
 * that webhook is missed, or if Shopify is told the request is accepted and the AW order then fails
 * to be created. This pulls the recent unfulfilled ones and feeds them through the same handler the
 * webhook uses; SweepShopifyMissedOrders runs it hourly over every connected store for the
 * accepted ones. One order that fails is reported and skipped so it cannot block the rest.
 *
 * The page size is deliberately small: Shopify charges GraphQL by query cost and the nested
 * fulfilment order and line item connections multiply quickly.
 */
class FetchShopifyOrdersFromApi
{
    use AsAction;
    use WithShopifyApi;
    use WithShopifyFulfilmentOrderPayload;

    public string $jobQueue = 'shopify';

    public int $jobTries = 1;

    private const int MAX_PAGES = 10;

    /**
     * @return int number of fulfilment orders that failed to import
     * @throws \Exception
     */
    public function handle(ShopifyUser $shopifyUser, int $days = 30, bool $acceptedOnly = false): int
    {
        $fields = $this->orderWithFulfilmentOrdersFields($shopifyUser);

        $query = <<<QUERY
            query getUnfulfilledOrders(\$query: String!, \$after: String) {
                orders(first: 20, after: \$after, query: \$query, sortKey: CREATED_AT, reverse: true) {
                    edges {
                        node {
                            $fields
                        }
                    }
                    pageInfo {
                        hasNextPage
                        endCursor
                    }
                }
            }
        QUERY;

        $variables = [
            'query' => 'fulfillment_status:unfulfilled AND created_at:>'.now()->subDays($days)->toDateString(),
            'after' => null,
        ];

        $failed = 0;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            list($success, $response) = $this->doPost($shopifyUser, $query, $variables);

            if (!$success) {
                if ($acceptedOnly && is_string($response) && $this->isStoreUnreachable($response)) {
                    return $failed;
                }

                throw new \Exception(is_string($response) ? $response : 'Shopify refused the request.');
            }

            $body = $response['body']->toArray();

            if ($errors = data_get($body, 'errors')) {
                throw new \Exception(json_encode($errors));
            }

            foreach (data_get($body, 'data.orders.edges', []) as $edge) {
                $order = data_get($edge, 'node');

                if (!$order) {
                    continue;
                }

                foreach ($this->buildFulfilmentOrderPayloads($shopifyUser, $order) as $fulfilmentOrder) {
                    if ($acceptedOnly && Arr::get($fulfilmentOrder, 'requestStatus') === 'SUBMITTED') {
                        continue;
                    }

                    try {
                        ImportShopifyFulfilmentOrder::run($shopifyUser, $fulfilmentOrder);
                    } catch (\Throwable $e) {
                        $failed++;
                        Log::warning('Shopify fulfilment order '.Arr::get($fulfilmentOrder, 'id').' of shopify user '.$shopifyUser->id.' failed to import: '.$e->getMessage());
                        Sentry::captureException($e);
                    }
                }
            }

            if (!data_get($body, 'data.orders.pageInfo.hasNextPage')) {
                break;
            }

            $variables['after'] = data_get($body, 'data.orders.pageInfo.endCursor');
        }

        return $failed;
    }

    /**
     * A store that is closed, unpaid or under review by Shopify, or whose client cannot be built
     * (reported once a day by getShopifyClient), is skipped by the sweep instead of failing hourly.
     */
    private function isStoreUnreachable(string $response): bool
    {
        return $response === 'Failed to initialize Shopify client'
            || preg_match('/^Error in API response: HTTP (402|404) /', $response)
            || str_contains($response, 'SHOP_PENDING_TERMINATION');
    }

    public string $commandSignature = 'shopify:fetch-orders {shopifyUser} {--days=30}';

    /**
     * @throws \Exception
     */
    public function asCommand(Command $command): int
    {
        $shopifyUser = ShopifyUser::find($command->argument('shopifyUser'));

        if (!$shopifyUser) {
            $command->error('Shopify user not found.');

            return 1;
        }

        Nightwatch::dontSample();

        $failed = $this->handle($shopifyUser, (int)$command->option('days'));

        if ($failed) {
            $command->error($failed.' fulfilment order(s) failed to import, see the log.');

            return 1;
        }

        $command->info('Done.');

        return 0;
    }
}
