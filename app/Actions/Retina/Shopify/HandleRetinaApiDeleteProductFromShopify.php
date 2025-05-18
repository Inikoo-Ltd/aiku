<?php

/*
 * author Arya Permana - Kirin
 * created on 17-01-2025-09h-56m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Shopify;

use App\Actions\Dropshipping\Shopify\Product\DeleteShopifyUserHasProduct;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Dropshipping\ShopifyUserHasProduct;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class HandleRetinaApiDeleteProductFromShopify extends RetinaAction
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
