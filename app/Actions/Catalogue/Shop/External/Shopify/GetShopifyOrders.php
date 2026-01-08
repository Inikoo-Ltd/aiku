<?php

namespace App\Actions\Catalogue\Shop\External\Shopify;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\ShopifyUser;

class GetShopifyOrders extends OrgAction
{
    public function handle(Shop $shop): array
    {
        $shopifyUser = ShopifyUser::where('external_shop_id', $shop->id)->first();

        return $shopifyUser->getShopifyOrders(['first' => 250]);
    }

    public function asCommand($command): int
    {
        $this->handle(Shop::where('slug', $command->argument('shop'))->first());

        return 0;
    }
}
