<?php

namespace App\Actions\Catalogue\Shop\External\Shopify;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;

class GetShopifyOrders extends OrgAction
{
    public function handle(Shop $shop): array
    {
        return $shop->getShopifyOrders();
    }
}
