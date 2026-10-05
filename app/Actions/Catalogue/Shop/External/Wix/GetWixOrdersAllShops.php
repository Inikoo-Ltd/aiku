<?php

namespace App\Actions\Catalogue\Shop\External\Wix;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;

class GetWixOrdersAllShops extends OrgAction
{
    public string $commandSignature = 'wix:orders';

    public function handle(Command|null $command = null): void
    {
        $shops = Shop::where('type', ShopTypeEnum::EXTERNAL)
            ->where('engine', ShopEngineEnum::WIX)
            ->where('state', ShopStateEnum::OPEN)
            ->whereHas('wixUser')
            ->get();

        foreach ($shops as $shop) {
            GetWixOrdersInShop::run($shop, $command);
        }
    }

    public function asCommand(Command $command): int
    {
        $this->handle($command);

        return 0;
    }
}
