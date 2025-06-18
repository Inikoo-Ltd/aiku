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

class RepairProductParents
{
    use WithActionUpdate;


    public function handle(Product $product, Command $command): void
    {
        if (!$product->family_id) {
            // $command->error('P: '.$product->code.' has no family');

            if ($product->sub_department_id) {
                $command->line('P: '.$product->code.' has no family but has sub_department_id '.$product->sub_department_id);
                dd('>>>>>>> E2');
            }

            if ($product->department_id) {
                $command->line('P: '.$product->code.' ('.$product->source_id.')  has no family but has department_id '.$product->department_id);
                $product->update(['department_id' => null]);
            }

            if ($product->sub_department_id) {
                $command->line('P: '.$product->code.' ('.$product->source_id.')  has no family but has sub_department_id '.$product->department_id);
                $product->update(['sub_department_id' => null]);
            }
        } else {

            if ($product->family->department_id != $product->department_id) {
                $command->error('P: '.$product->code.' ('.$product->source_id.')  has family_id '.$product->family_id.' but department_id '.$product->department_id);
                $product->update(['department_id' => $product->family->department_id]);
            }

            if ($product->family->sub_department_id != $product->sub_department_id) {
                $command->error('P: '.$product->code.' ('.$product->source_id.')  has family_id '.$product->family_id.' with sub dep  ('.$product->family->sub_department_id.')   but prod diff  sub_department_id '.$product->sub_department_id);
                dd('>>>>>>> E3');
            }

        }
    }


    public string $commandSignature = 'repair:product_parents {product?}';

    public function asCommand(Command $command): void
    {
        if ($command->argument('product')) {
            $product = Product::find($command->argument('product'));
            $this->handle($product, $command);
        } else {
            $count = Product::count();

            $bar = $command->getOutput()->createProgressBar($count);
            $bar->setFormat('debug');
            $bar->start();

            Product::orderBy('id')
                ->chunk(100, function (Collection $models) use ($bar, $command) {
                    foreach ($models as $model) {
                        $this->handle($model, $command);
                        $bar->advance();
                    }
                });
        }
    }

}
