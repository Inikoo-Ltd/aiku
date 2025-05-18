<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Dropshipping\ShopifyUserHasProduct;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class HandleApiDeleteProductFromShopify extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(ShopifyUser $shopifyUser, ShopifyUserHasProduct $product): void
    {
        $client   = $shopifyUser->api()->getRestClient();
        $response =  $client->request('DELETE', '/admin/api/2024-04/products/'.$product->shopify_product_id.'.json');

        if ($response['status'] == 422) {
            abort($response['status'], $response['body']);
        }

        if ($response['status'] == 200) {
            DeleteShopifyUserHasProduct::run($product);
        }
    }
}
