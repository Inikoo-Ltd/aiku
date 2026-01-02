<?php

namespace App\Actions\Catalogue\Shop\External\Shopify;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;

class GetShopifyCustomers extends OrgAction
{
    public function handle(Shop $shop, string $retailerId): array
    {
        return $shop->getFaireRetailers($retailerId);
    }
}
