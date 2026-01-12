<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 12 Jan 2026 17:33:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Product;
use App\Models\Helpers\Language;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RepairProductIsReviewed
{
    use WithActionUpdate;


    public string $commandSignature = 'products:repair_reviewed_fields';

    public function asCommand(Command $command): void
    {
        $fields = [
            'name',
            'description',
            'description_title',
            'description_extra'
        ];

        $english = Language::where('code', 'en')->first();

        $totalProducts = Product::whereNotNull('master_product_id')->count();
        $progressBar   = $command->getOutput()->createProgressBar($totalProducts);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $progressBar->start();

        Product::whereNotNull('master_product_id')
            ->orderBy('id')
            ->chunk(100, function (Collection $products) use ($command, $fields, $english, $progressBar) {
                foreach ($products as $product) {

                    $shop = $product->shop;

                    if (!$shop->language_id == $english->id) {
                        continue;
                    }


                    if (!$shop->masterShop) {
                        continue;
                    }



                    foreach ($fields as $field) {
                        if ($product->{$field} == '') {
                            $product->update(
                                [
                                    'is_'.$field.'_reviewed' => false
                                ]
                            );
                        }
                    }


                    $masterProduct = $product->masterProduct;
                    if (!$masterProduct) {
                        continue;
                    }


                    foreach ($fields as $field) {
                        if ($product->{$field} == $masterProduct->{$field}) {
                            $product->update(
                                [
                                    'is_'.$field.'_reviewed' => false
                                ]
                            );
                            break;
                        }

                        if ($masterProduct->{$field} == '' && $product->{$field} != '') {
                            $product->update(
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
