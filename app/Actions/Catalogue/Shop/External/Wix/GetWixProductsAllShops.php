<?php

namespace App\Actions\Catalogue\Shop\External\Wix;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;

class GetWixProductsAllShops extends OrgAction
{
    public string $commandSignature = 'wix:products_all_shops';

    public $jobQueue = 'long-running';

    public function handle(Command|null $command = null): void
    {
        $shops = Shop::where('type', ShopTypeEnum::EXTERNAL)
            ->where('engine', ShopEngineEnum::WIX)
            ->where('state', ShopStateEnum::OPEN)
            ->whereHas('wixUser')
            ->get();

        foreach ($shops as $shop) {
            GetWixProducts::run($shop, $command);
        }
    }

    public function asCommand(Command $command): void
    {
        $this->handle($command);
    }
}
