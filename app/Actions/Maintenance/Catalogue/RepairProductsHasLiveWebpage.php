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

        $query = Product::query();

        $total = (clone $query)->count();

        $progressBar   = $command->getOutput()->createProgressBar($total);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $progressBar->start();

        $query
            ->orderBy('id')
            ->chunkById(1000, function ($products) use (&$progressBar) {
                foreach ($products as $product) {
                    $this->handle($product);
                    $progressBar->advance();
                }
            });

        $progressBar->finish();
        $command->newLine();
    }

}
