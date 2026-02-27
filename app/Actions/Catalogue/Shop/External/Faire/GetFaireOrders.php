<?php

namespace App\Actions\Catalogue\Shop\External\Faire;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class GetFaireOrders extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Command|null $command = null): void
    {
        $shops = Shop::where('type', ShopTypeEnum::EXTERNAL)
            ->where('engine', ShopEngineEnum::FAIRE)
            ->get();

        /** @var Shop $shop */
        foreach ($shops as $shop) {
            if (Arr::has($shop->settings, 'faire.access_token')) {
                GetFaireOrdersInShop::run($shop, $command);
            }
        }

    }

    public string $commandSignature = 'faire:orders';


    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $this->handle($command);

        return 0;
    }
}
