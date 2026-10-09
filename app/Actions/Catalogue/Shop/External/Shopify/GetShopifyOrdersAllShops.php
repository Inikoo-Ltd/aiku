<?php

namespace App\Actions\Catalogue\Shop\External\Shopify;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;

class GetShopifyOrdersAllShops extends OrgAction
{
    public string $commandSignature = 'external_shop:shopify_orders_all_shops';

    public function handle(?Command $command = null): void
    {
        $shops = Shop::where('type', ShopTypeEnum::EXTERNAL)
            ->where('engine', ShopEngineEnum::SHOPIFY)
            ->where('state', ShopStateEnum::OPEN)
            ->whereHas('externalShopifyUser')
            ->get();

        foreach ($shops as $shop) {
            GetShopifyOrdersInShop::run($shop, $command);
        }
    }

    public function asCommand(Command $command): int
    {
        $this->handle($command);

        return 0;
    }
}
