<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 02 Sept 2026 15:31:44 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Order;

use App\Actions\Dropshipping\Shopify\Fulfilment\Webhooks\CreateFulfilmentOrderFromShopify;
use App\Actions\Dropshipping\Shopify\WithShopifyApi;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Shopify orders reach AW through the fulfillment_order_notification webhook, with no poller to
 * fall back on, so an order is lost for good if that webhook is missed. This pulls the recent
 * unfulfilled ones and feeds them through the same handler the webhook uses.
 *
 * The page size is deliberately small: Shopify charges GraphQL by query cost and the nested
 * fulfilment order and line item connections multiply quickly.
 */
class FetchShopifyOrdersFromApi
{
    use AsAction;
    use WithShopifyApi;
    use WithShopifyFulfilmentOrderPayload;

    /**
     * @throws \Exception
     */
    public function handle(ShopifyUser $shopifyUser, int $days = 30): void
    {
        $fields = $this->orderWithFulfilmentOrdersFields();

        $query = <<<QUERY
            query getUnfulfilledOrders(\$query: String!) {
                orders(first: 20, query: \$query, sortKey: CREATED_AT, reverse: true) {
                    edges {
                        node {
                            $fields
                        }
                    }
                }
            }
        QUERY;

        $variables = [
            'query' => 'fulfillment_status:unfulfilled AND created_at:>'.now()->subDays($days)->toDateString(),
        ];

        list($success, $response) = $this->doPost($shopifyUser, $query, $variables);

        if (!$success) {
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

            $fulfilmentOrder = $this->buildFulfilmentOrderPayload($order);

            if (!$fulfilmentOrder) {
                continue;
            }

            CreateFulfilmentOrderFromShopify::run($shopifyUser, $fulfilmentOrder);
        }
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

        $this->handle($shopifyUser, (int)$command->option('days'));

        $command->info('Done.');

        return 0;
    }
}
