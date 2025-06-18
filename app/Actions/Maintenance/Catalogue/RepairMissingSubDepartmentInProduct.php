<?php

/*
 * author Arya Permana - Kirin
 * created on 02-06-2025-15h-25m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Catalogue\ProductCategory\Hydrators\SubDepartmentHydrateProducts;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RepairMissingSubDepartmentInProduct
{
    use WithActionUpdate;


    public function handle(Product $product): void
    {
        if ($product->family && $product->family->subDepartment && !$product->subDepartment) {
            $product = $this->update($product, ['sub_department_id' => $product->family->subDepartment->id]);
            $product->refresh();
            SubDepartmentHydrateProducts::run($product->subDepartment);
        }
    }


    public string $commandSignature = 'repair:products_null_sub_department {productCategory?}';

    public function asCommand(Command $command): void
    {
        if ($command->argument('productCategory')) {
            $productCategory = ProductCategory::find($command->argument('productCategory'));
            $products = $productCategory->getProducts()->whereNull('sub_department_id');
            foreach ($products as $product) {
                $this->handle($product);
            }
        } else {
            $count = Product::whereNotNull('family_id')->whereNull('sub_department_id')->count();

            $bar = $command->getOutput()->createProgressBar($count);
            $bar->setFormat('debug');
            $bar->start();

            Product::orderBy('id')->whereNotNull('family_id')->whereNull('sub_department_id')
                ->chunk(100, function (Collection $models) use ($bar) {
                    foreach ($models as $model) {
                        $this->handle($model);
                        $bar->advance();
                    }
                });
        }
    }

}
