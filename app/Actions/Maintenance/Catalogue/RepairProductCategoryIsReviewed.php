<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 12 Jan 2026 11:39:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\ProductCategory;
use App\Models\Helpers\Language;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RepairProductCategoryIsReviewed
{
    use WithActionUpdate;


    public string $commandSignature = 'product_categories:repair_reviewed_fields';

    public function asCommand(Command $command): void
    {
        $fields = [
            'name',
            'description',
            'description_title',
            'description_extra'
        ];

        $english = Language::where('code', 'en')->first();
        $totalProductCategories = ProductCategory::whereNotNull('master_product_category_id')->count();
        $progressBar            = $command->getOutput()->createProgressBar($totalProductCategories);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $progressBar->start();

        ProductCategory::whereNotNull('master_product_category_id')
            ->orderBy('id')
            ->chunk(100, function (Collection $productCategories) use ($command, $fields, $english, $progressBar) {
                foreach ($productCategories as $productCategory) {

                    $shop = $productCategory->shop;

                    if (!$shop->language_id == $english->id) {
                        continue;
                    }


                    if (!$shop->masterShop) {
                        continue;
                    }



                    foreach ($fields as $field) {
                        if ($productCategory->{$field} == '') {
                            $productCategory->update(
                                [
                                    'is_'.$field.'_reviewed' => false
                                ]
                            );
                        }
                    }


                    $masterProductCategory = $productCategory->masterProductCategory;
                    if (!$masterProductCategory) {
                        continue;
                    }


                    foreach ($fields as $field) {
                        if ($productCategory->{$field} == $masterProductCategory->{$field}) {
                            $productCategory->update(
                                [
                                    'is_'.$field.'_reviewed' => false
                                ]
                            );
                            break;
                        }

                        if ($masterProductCategory->{$field} == '' && $productCategory->{$field} != '') {
                            $productCategory->update(
                                [
                                    'is_'.$field.'_reviewed' => true
                                ]
                            );
                            break;
                        }




                    }

                    $progressBar->advance();
                }
            });

        $progressBar->finish();
        $command->newLine();
    }
}
