<?php

/*
 * author Arya Permana - Kirin
 * created on 02-06-2025-15h-25m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RepairProductCategoryParents
{
    use WithActionUpdate;


    public function handle(ProductCategory $productCategory, Command $command): void
    {
        switch ($productCategory->type) {
            case ProductCategoryTypeEnum::SUB_DEPARTMENT:
                if (!$productCategory->parent_id) {
                    $command->error('Sub Department '.$productCategory->code.' has no parent');
                    dd('>>>>>>> E3');
                }

                if ($productCategory->parent_id != $productCategory->department_id) {
                    $command->error('Sub Department '.$productCategory->code.' has parent_id '.$productCategory->parent_id.' but department_id '.$productCategory->department_id);
                    dd('>>>>>>> E5');
                }
                break;
            case ProductCategoryTypeEnum::FAMILY:
                if (!$productCategory->parent_id) {
                    //$command->error('Family '.$productCategory->code.' has no parent');

                    if ($productCategory->department_id) {
                        $command->line('Family '.$productCategory->code.' has no parent but has department_id '.$productCategory->department_id);
                        dd('>>>>>>> E1');
                    }

                    if ($productCategory->sub_department_id) {
                        $command->line('Family '.$productCategory->code.' has no parent but has seb_department_id '.$productCategory->department_id);
                        dd('>>>>>>> E4');
                    }

                } else {

                    if ($productCategory->sub_department_id) {
                        if ($productCategory->parent_id != $productCategory->sub_department_id) {
                            $command->error('Family '.$productCategory->code.' has parent_id '.$productCategory->parent_id.' but sub_department_id '.$productCategory->sub_department_id);
                            dd('>>>>>>> E6');
                        }

                        if ($productCategory->department_id != $productCategory->subDepartment->department_id) {
                            $command->error('Family '.$productCategory->code.' has department_id '.$productCategory->department_id.' but sub_department_id '.$productCategory->sub_department_id);
                            dd('>>>>>>> E7');
                        }

                    }


                }
                break;
            case ProductCategoryTypeEnum::DEPARTMENT:
                if ($productCategory->parent_id) {
                    $command->error('Department '.$productCategory->code.' has  parent :S');
                    dd('>>>>>>> E2');
                }
                break;
        }
    }


    public string $commandSignature = 'repair:product_category_parents {productCategory?}';

    public function asCommand(Command $command): void
    {
        if ($command->argument('productCategory')) {
            $productCategory = ProductCategory::find($command->argument('productCategory'));
            $this->handle($productCategory, $command);
        } else {
            $count = ProductCategory::count();

            $bar = $command->getOutput()->createProgressBar($count);
            $bar->setFormat('debug');
            $bar->start();

            ProductCategory::orderBy('id')
                ->chunk(100, function (Collection $models) use ($bar, $command) {
                    foreach ($models as $model) {
                        $this->handle($model, $command);
                        // $bar->advance();
                    }
                });
        }
    }

}
