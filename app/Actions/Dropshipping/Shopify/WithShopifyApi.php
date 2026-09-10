<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 22 Jul 2025 16:47:09 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify;

use App\Models\Dropshipping\ShopifyUser;

/**
 * The REST client reports a throttled or malformed GraphQL call as a successful HTTP reply whose body
 * carries the errors and no data, so that case is surfaced here for every caller. A reply that carries
 * data next to errors (a field the store's scopes do not cover) is still handed on as it always was.
 */
trait WithShopifyApi
{
    public function doPost(ShopifyUser $shopifyUser, $mutation, $variables): array
    {
        $client = $shopifyUser->getShopifyClient();

        if (!$client) {
            return [false, 'Failed to initialize Shopify client'];
        }

        $response = $client->request('POST', '/admin/api/2025-07/graphql.json', [
            'json' => [
                'query'     => $mutation,
                'variables' => $variables ?: new \stdClass()
            ]
        ]);

        if (!empty($response['errors']) || !isset($response['body'])) {
            $detail = $response['errors'] === true
                ? 'HTTP '.($response['status'] ?? '?').' '.json_encode($response['body'] ?? $response['exception']?->getMessage())
                : json_encode($response['errors'] ?? []);

            return [false, 'Error in API response: '.trim($detail)];
        }

        $body = $response['body']->toArray();
        if (!empty($body['errors']) && empty($body['data'])) {
            return [false, 'Error in API response: '.json_encode($body['errors'])];
        }

        return [true, $response];
    }

}
