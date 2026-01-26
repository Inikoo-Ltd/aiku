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
use Illuminate\Console\Command;

class RepairProductsHasLiveWebpage
{
    use WithActionUpdate;

    public string $commandSignature = 'product:repair_has_live_webpage';

    public function handle(Product $product)
    {
        ProductHydrateHasLiveWebpage::run($product);
    }

    public function asCommand(Command $command): void
    {
        $command->info('Repairing Products has_live_webpage');

        $query = Product::all();

        $total = (clone $query)->count();

        $bar = $command->getOutput()->createProgressBar($total);
        $bar->start();

        $query->chunk(200, function ($products) use ($bar) {
            foreach ($products as $product) {
                $this->handle($product);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->newLine();
    }

}
