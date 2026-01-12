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

class TranslateModel
{
    use AsAction;

    public function handle(ProductCategory|Product $model, array $translationData, bool $overwrite = false): void
    {
        $english      = Language::where('code', 'en')->first();
        $shopLanguage = $model->shop->language;

        $modelData = [];


        if ($model instanceof Product && Arr::get($translationData, 'unit') && (!$model->unit || $overwrite)) {
            data_set($modelData, 'unit', Translate::run($translationData['unit'], $english, $shopLanguage));
        }
        if (Arr::get($translationData, 'name') && (!$model->name || $overwrite)) {
            data_set($modelData, 'name', Translate::run($translationData['name'], $english, $shopLanguage));
        }
        if (Arr::get($translationData, 'description') && (!$model->description || $overwrite)) {
            data_set($modelData, 'description', Translate::run($translationData['description'], $english, $shopLanguage));
        }
        if (Arr::get($translationData, 'description_title') && (!$model->description_title || $overwrite)) {
            data_set($modelData, 'description_title', Translate::run($translationData['description_title'], $english, $shopLanguage));
        }
        if (Arr::get($translationData, 'description_extra') && (!$model->description_extra || $overwrite)) {
            data_set($modelData, 'description_extra', Translate::run($translationData['description_extra'], $english, $shopLanguage));
        }
        
        if ($model instanceof ProductCategory) {
            UpdateProductCategory::run($model, $modelData);
        } else {
            UpdateProduct::run($model, $modelData);
        }
    }

}
