<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 17 Sept 2025 18:03:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Translations;

use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Catalogue\ProductCategory\UpdateProductCategory;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Helpers\Language;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class TranslateCategoryModel
{
    use AsAction;

    public function getJobUniqueId(ProductCategory|Product $model): string
    {
        return class_basename($model).$model->id;
    }

    public function handle(ProductCategory|Product $model, array $translationData): void
    {
        $english = Language::where('code', 'en')->first();
        $shopLanguage = $model->shop->language;

        $modelData = [
            'name'                       => Translate::run($translationData['name'], $english, $shopLanguage),
            'description'                => Translate::run($translationData['description'], $english, $shopLanguage),
            'description_title'          => Translate::run($translationData['description_title'], $english, $shopLanguage),
            'description_extra'          => Translate::run($translationData['description_extra'], $english, $shopLanguage),

        ];

        if($shopLanguage->code == 'en') {
            data_set($modelData, 'is_name_reviewed', true);
            data_set($modelData, 'is_description_title_reviewed', true);
            data_set($modelData, 'is_description_reviewed', true);
            data_set($modelData, 'is_description_extra_reviewed', true);

        }else{
            data_set($modelData, 'is_name_reviewed', Arr::get(
                $translationData,
                'name',
                false
            )?false:null);

            data_set($modelData, 'is_description_title_reviewed', Arr::get(
                $translationData,
                'description_title',
                false
            )?false:null);

            data_set($modelData, 'is_description_reviewed', Arr::get(
                $translationData,
                'description',
                false
            )?false:null);

            data_set($modelData, 'is_description_extra_reviewed', Arr::get(
                $translationData,
                'description_extra',
                false
            )?false:null);

        }


        if ($model instanceof ProductCategory) {
            UpdateProductCategory::run($model, $modelData);
        } else {
            UpdateProduct::run($model, $modelData);
        }

    }

}
