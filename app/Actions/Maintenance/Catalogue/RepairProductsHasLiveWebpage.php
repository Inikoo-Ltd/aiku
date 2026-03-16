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
use Symfony\Component\Console\Helper\ProgressBar;

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

        ProgressBar::setFormatDefinition(
            'aiku_eta',
            ' %current%/%max% [%bar%] %percent:3s%% | Elapsed: %elapsed:6s% | ETA: %remaining:6s%'
        );
        $bar = $command->getOutput()->createProgressBar($total);
        $bar->setFormat('aiku_eta');
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
