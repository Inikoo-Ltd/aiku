<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\ShopifyUserHasProduct;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class DeleteShopifyUserHasProduct extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(?ShopifyUserHasProduct $product, $forceDelete = false): ShopifyUserHasProduct|null|int
    {
        if (!$product) {
            return null;
        }

        $shopifyUser = $product->shopifyUser;
        $shopifyUser->api()->getRestClient()->request('DELETE', '/admin/api/2025-04/products/'.$product->shopify_product_id.'.json');

        if ($forceDelete) {
            return $product->delete();
        }

        return $this->update($product, ['shopify_product_id' => null]);
    }
}
