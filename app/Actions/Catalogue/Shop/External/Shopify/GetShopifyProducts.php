<?php

namespace App\Actions\Catalogue\Shop\External\Shopify;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\ShopifyUser;

class GetShopifyProducts extends OrgAction
{
    public string $commandSignature = 'external_shop:shopify_products {shop}';

    public function handle(Shop $shop): array
    {
        $shopifyUser = ShopifyUser::where('external_shop_id', $shop->id)->first();

        return $shopifyUser->getShopifyProducts(['first' => 250]);
    }

    public function asCommand($command): int
    {
        $this->handle(Shop::where('slug', $command->argument('shop'))->first());

        return 0;
    }
}
