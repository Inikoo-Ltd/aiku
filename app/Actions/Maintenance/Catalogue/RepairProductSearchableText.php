<?php

/*
 * author Louis Perez
 * created on 02-03-2026-09h-31m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Product;
use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\ProgressBar;

class RepairProductSearchableText
{
    use WithActionUpdate;

    protected function handle(Product $product): void
    {
        $product->touch();
    }

    public string $commandSignature = 'products:repair_searchable_text';

    public function asCommand(Command $command): void
    {
        $products = Product::query();
        $total = (clone $products)->count();

        ProgressBar::setFormatDefinition(
            'aiku_eta',
            ' %current%/%max% [%bar%] %percent:3s%% | Elapsed: %elapsed:6s% | ETA: %remaining:6s%'
        );
        $bar = $command->getOutput()->createProgressBar($total);
        $bar->setFormat('aiku_eta');
        $bar->start();

        $products->chunk(200, function ($products) use ($bar) {
            foreach ($products as $product) {
                $this->handle($product);
                $bar->advance();
            }
        });

        $bar->finish();

    }

}
