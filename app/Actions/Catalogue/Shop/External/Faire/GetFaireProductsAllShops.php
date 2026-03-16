<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 01 Mar 2026 22:05:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\External\Faire;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class GetFaireProductsAllShops extends OrgAction
{
    public string $commandSignature = 'faire:products_all_shops';

    public $jobQueue = 'default-long';

    public function handle(Command|null $command = null): void
    {
        $shops = Shop::where('type', ShopTypeEnum::EXTERNAL)
            ->where('engine', ShopEngineEnum::FAIRE)
            ->get();

        /** @var Shop $shop */
        foreach ($shops as $shop) {
            if (Arr::has($shop->settings, 'faire.access_token')) {
                GetFaireProducts::run($shop, [], $command);
            }
        }
    }

    public function asCommand(Command $command): void
    {
        $this->handle($command);
    }


}
