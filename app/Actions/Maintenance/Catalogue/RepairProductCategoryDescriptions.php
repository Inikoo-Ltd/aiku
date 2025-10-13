<?php

/*
 * author Arya Permana - Kirin
 * created on 02-06-2025-15h-25m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Helpers\Translations\Translate;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\ProductCategory;
use App\Models\Helpers\Language;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RepairProductCategoryDescriptions
{
    use WithActionUpdate;


    public function handle(ProductCategory $productCategory, Command $command): void
    {
        $english      = Language::where('code', 'en')->first();
        $shopLanguage = $productCategory->shop->language;

        $masterProductCategory = $productCategory->masterProductCategory;
        if ($masterProductCategory) {
            if (!$masterProductCategory->description_title && $productCategory->description_title) {
                $command->line('P: '.$productCategory->code."  [{$productCategory->shop->slug}]  ($shopLanguage->code)  ".$productCategory->description_title);

                if ($shopLanguage->code == 'en') {
                    $masterProductCategory->updateQuietly([
                        'description_title' => $productCategory->description_title,
                    ]);
                } else {
                    $productCategory->updateQuietly([
                        'description_title' => ''
                    ]);
                    $productCategory->forgetTranslation('description_title_i8n', $shopLanguage->code);
                }
            }

            if ($masterProductCategory->description_title && !$productCategory->description_title) {
                $command->line('Q: '.$productCategory->code."  [{$productCategory->shop->slug}]  ($shopLanguage->code)  ".$masterProductCategory->description_title);

                $translation = Translate::run($masterProductCategory->description_title, $english, $shopLanguage);
                $productCategory->updateQuietly([
                    'description_title' => $translation,
                ]);
                $productCategory->setTranslation('description_title_i8n', $shopLanguage->code, $translation);
            }
        }
    }


    public string $commandSignature = 'repair:product_category_description {productCategory?}';

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
