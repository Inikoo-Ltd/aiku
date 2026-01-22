<?php

/*
 * author Louis Perez
 * created on 22-01-2026-13h-45m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Catalogue\Product\Hydrators\ProductHydrateHasLiveWebpage;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;

class RepairProductsHasLiveWebpage
{
    use WithActionUpdate;

    public string $commandSignature = 'product:repair_has_live_webpage';

    public function handle(Product $product): void
    {
        ProductHydrateHasLiveWebpage::run($product);
    }

    public function asCommand(Command $command): void
    {

        $aikuShops=Shop::where('is_aiku',true)->pluck('id')->toArray();

        $command->info('Repairing Products has_live_webpage');

        $total = Product::whereIn('shop_id',$aikuShops)->count();

        $bar = $command->getOutput()->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();

        Product::whereIn('shop_id',$aikuShops)->chunk(200, function ($products) use ($bar) {
            foreach ($products as $product) {
                $this->handle($product);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->newLine();
    }

}
