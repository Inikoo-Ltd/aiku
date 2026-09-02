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

    public function handle(ShopifyUser $shopifyUser, string $orderId): array
    {
        $fields = $this->orderWithFulfilmentOrdersFields();

        $query = <<<QUERY
            query getFulfilmentOrder(\$id: ID!) {
                order(id: \$id) {
                    $fields
                }
            }
        QUERY;

        list($success, $response) = $this->doPost($shopifyUser, $query, ['id' => $this->resolveGid($orderId)]);

        if (!$success) {
            return [];
        }

        $order = data_get($response['body']->toArray(), 'data.order');

        return $order ? $this->buildFulfilmentOrderPayload($order) : [];
    }

    private function resolveGid(string $orderId): string
    {
        return str_starts_with($orderId, 'gid://') ? $orderId : 'gid://shopify/Order/'.$orderId;
    }
}
