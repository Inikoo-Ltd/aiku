<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 22 Jul 2025 16:47:09 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify;

use App\Models\Dropshipping\ShopifyUser;

trait WithShopifyApi
{
    public function doPost(ShopifyUser $shopifyUser, $mutation, $variables): array
    {
        $client   = $shopifyUser->getShopifyClient();
        $response = $client->request('POST', '/admin/api/2025-07/graphql.json', [
            'json' => [
                'query'     => $mutation,
                'variables' => $variables
            ]
        ]);

        if (!empty($response['errors']) || !isset($response['body'])) {
            return [false, 'Error in API response: '.json_encode($response['errors'] ?? [])];
        }

        return [true, $response];
    }

}
