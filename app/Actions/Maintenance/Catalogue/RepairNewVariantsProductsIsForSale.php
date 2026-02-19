<?php

/*
 * author Arya Permana - Kirin
 * created on 02-06-2025-15h-25m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RepairNewVariantsProductsIsForSale
{
    use WithActionUpdate;


    public function handle(Product $product, Command $command): void
    {
        $product->update(['is_for_sale' => true]);
    }


    public string $commandSignature = 'repair:new_variants_products_is_for_sale';

    public function asCommand(Command $command): void
    {
        $count = Product::whereNotNull('variant_id')->count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        Product::whereNotNull('variant_id')->orderBy('id')
            ->chunk(100, function (Collection $models) use ($bar, $command) {
                foreach ($models as $model) {
                    $this->handle($model, $command);
                    $bar->advance();
                }
            });
    }

}
