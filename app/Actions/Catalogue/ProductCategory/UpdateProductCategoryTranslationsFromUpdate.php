<?php

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\OrgAction;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateProductCategoryTranslationsFromUpdate extends OrgAction
{
    use asAction;

    public function handle(ProductCategory $productCategory, array $modelData): ProductCategory
    {
        $name_i8n = [];
        $description_i8n = [];
        $description_title_i8n = [];
        $description_extra_i8n = [];
        
        if(Arr::has($modelData, 'translations.name')) {
            foreach ($modelData['translations']['name'] as $locale => $translation) {
                $name_i8n[$locale] = $translation;
                $productCategory->name_i8n = $name_i8n;
            }
        }
        if(Arr::has($modelData, 'translations.description_title')) {
            foreach ($modelData['translations']['description_title'] as $locale => $translation) {
                $description_title_i8n[$locale] = $translation;
                $productCategory->description_title_i8n = $description_title_i8n;
            }
        }
        if(Arr::has($modelData, 'translations.description')) {
            foreach ($modelData['translations']['description'] as $locale => $translation) {
                $description_i8n[$locale] = $translation;
                $productCategory->description_i8n = $description_i8n;
            }
        }
        if(Arr::has($modelData, 'translations.description_extra')) {
            foreach ($modelData['translations']['description_extra'] as $locale => $translation) {
                $description_extra_i8n[$locale] = $translation;
                $productCategory->description_extra_i8n = $description_extra_i8n;
            }
        }

        $productCategory->save();


        return $productCategory;


    }

    public function rules(): array
    {
        return [
            'translations' => ['required', 'array'],
        ];
    }

    public function action(ProductCategory $productCategory, array $modelData): void
    {
        $this->initialisationFromShop($productCategory->shop, $modelData);
        $this->handle($productCategory, $this->validatedData);
    }

}
