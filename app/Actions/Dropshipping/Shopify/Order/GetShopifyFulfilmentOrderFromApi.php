<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 02 Sept 2026 14:41:55 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Order;

use App\Actions\Dropshipping\Shopify\WithShopifyApi;
use App\Models\Dropshipping\ShopifyUser;
use Lorisleiva\Actions\Concerns\AsObject;

class GetShopifyFulfilmentOrderFromApi
{
    use AsObject;
    use WithShopifyApi;
    use WithShopifyFulfilmentOrderPayload;

    /**
     * Null means Shopify has no such order; an empty array means the order is there but holds no
     * fulfilment request for AW, which is a different answer to give whoever retries the import.
     *
     * @throws \Exception
     */
    public function handle(ShopifyUser $shopifyUser, string $orderId): ?array
    {
        $fields = $this->orderWithFulfilmentOrdersFields($shopifyUser);

        $query = <<<QUERY
            query getFulfilmentOrder(\$id: ID!) {
                order(id: \$id) {
                    $fields
                }
            }
        QUERY;

        list($success, $response) = $this->doPost($shopifyUser, $query, ['id' => $this->resolveGid($orderId)]);

        if (!$success) {
            throw new \Exception(is_string($response) ? $response : 'Shopify refused the request.');
        }

        $order = data_get($response['body']->toArray(), 'data.order');

        return $order ? ($this->buildFulfilmentOrderPayloads($shopifyUser, $order)[0] ?? []) : null;
    }

    private function resolveGid(string $orderId): string
    {
        return str_starts_with($orderId, 'gid://') ? $orderId : 'gid://shopify/Order/'.$orderId;
    }
}
