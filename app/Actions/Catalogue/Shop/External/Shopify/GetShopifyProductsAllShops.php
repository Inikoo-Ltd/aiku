<?php

namespace App\Actions\Catalogue\Shop\External\Shopify;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;

class GetShopifyProductsAllShops extends OrgAction
{
    public string $commandSignature = 'external_shop:shopify_products_all_shops';

    public $jobQueue = 'long-running';

    public function handle(?Command $command = null): void
    {
        $shops = Shop::where('type', ShopTypeEnum::EXTERNAL)
            ->where('engine', ShopEngineEnum::SHOPIFY)
            ->where('state', ShopStateEnum::OPEN)
            ->whereHas('externalShopifyUser')
            ->get();

        foreach ($shops as $shop) {
            GetShopifyProducts::run($shop, $command);
        }
    }

    public function asCommand(Command $command): void
    {
        $this->handle($command);
    }
}
