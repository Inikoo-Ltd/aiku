<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 May 2025 11:05:58 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\SysAdmin;

use App\Actions\Catalogue\Shop\StoreShopPlatformStats;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class AddMissingPlatformStatToShops
{
    use WithActionUpdate;


    public function handle(Shop $shop): void
    {
        StoreShopPlatformStats::run($shop);
    }


    public string $commandSignature = 'maintenance:add_missing_platform_stat_to_shops';

    public function asCommand(Command $command): void
    {
        $count = Shop::count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        Shop::orderBy('id')
            ->chunk(100, function (Collection $models) use ($bar) {
                foreach ($models as $model) {
                    $this->handle($model);
                    $bar->advance();
                }
            });
    }

}
