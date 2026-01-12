<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 12 Jan 2026 11:39:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Helpers\Translations\TranslateModel;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RedoProductCategoryTranslationsFromMaster
{
    use WithActionUpdate;


    public string $commandSignature = 'product_categories:redo_translations_from_master {shop} {type}';

    public function asCommand(Command $command): void
    {
        $fields = [
            'name',
            'description',
            'description_title',
            'description_extra'
        ];

        $shop = Shop::where('slug', $command->argument('shop'))->firstOrFail();


        ProductCategory::whereNotNull('master_product_category_id')
            ->where('shop_id', $shop->id)
            ->where('type', $command->argument('type'))
            ->orderBy('id')
            ->chunk(100, function (Collection $productCategories) use ($command, $fields) {
                foreach ($productCategories as $productCategory) {
                    $masterProductCategory = $productCategory->masterProductCategory;
                    if (!$masterProductCategory) {
                        $command->error('No master product category found for shop '.$command->argument('shop'));
                        continue;
                    }

                    foreach ($fields as $field) {
                        if ($masterProductCategory->{$field} != '') {
                            $command->info("Repairing category $field $productCategory->slug  from  $masterProductCategory->slug ");
                            TranslateModel::dispatch(
                                model: $productCategory,
                                translationData: [
                                    $field => $masterProductCategory->{$field}
                                ]
                            );
                        }
                    }
                }
            });
    }

}
