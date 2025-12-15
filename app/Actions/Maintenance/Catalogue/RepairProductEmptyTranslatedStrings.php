<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 15 Dec 2025 15:13:18 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Helpers\Translations\TranslateCategoryModel;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RepairProductEmptyTranslatedStrings
{
    use WithActionUpdate;


    public string $commandSignature = 'repair:empty_translated_string';

    public function asCommand(Command $command): void
    {
        $fields = [
            'unit',
            'name',
            'description',
            'description_title',
            'description_extra'
        ];

        foreach ($fields as $field) {
            Product::whereNotNull('master_product_id')
                ->where(function ($q) use ($field) {
                    $q->where($field, '')->orWhereNull($field);
                })
                ->orderBy('id')
                ->chunk(100, function (Collection $products) use ($command, $field) {
                    foreach ($products as $product) {
                        $masterProduct = $product->masterProduct;
                        if ($masterProduct->{$field}) {
                            $command->info("Repairing product $field $product->slug  from  $masterProduct->slug ");

                            TranslateCategoryModel::run(
                                $product,
                                [
                                    $field=> $masterProduct->{$field}
                                ]
                            );
                        }
                    }
                });
        }
    }

}
