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
    /**
     * The client is built with the library's 10 s timeout (the 90 s set in WithInitShopifyClient comes too late
     * to apply), so a call Shopify is known to answer slowly passes its own.
     */
    public function doPost(ShopifyUser $shopifyUser, $mutation, $variables, string $apiVersion = '2025-07', ?float $timeout = null): array
    {
        $client = $shopifyUser->getShopifyClient();

        if (!$client) {
            return [false, 'Failed to initialize Shopify client'];
        }

        $response = $client->request('POST', "/admin/api/$apiVersion/graphql.json", [
            'json' => [
                'query'     => $mutation,
                'variables' => $variables ?: new \stdClass()
            ],
            ...($timeout ? ['timeout' => $timeout] : []),
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

    /**
     * Shopify answers an assigned fulfilment orders query this way when the token's app owns no fulfilment
     * service in the store. A service with our name can still be listed there, yet no order of that store
     * reaches us.
     */
    public function fulfilmentServiceIsLost(string $message): bool
    {
        return str_contains($message, 'not associated with any fulfillment service');
    }

}
