<?php

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\OrgAction;
use App\Models\Catalogue\ProductCategory;
use App\Models\Masters\MasterProductCategory;
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

        if (Arr::has($modelData, 'translations.name')) {
            foreach ($modelData['translations']['name'] as $locale => $translation) {
                $name_i8n[$locale] = $translation;
                $productCategory->name_i8n = $name_i8n;
            }
        }
        if (Arr::has($modelData, 'translations.description_title')) {
            foreach ($modelData['translations']['description_title'] as $locale => $translation) {
                $description_title_i8n[$locale] = $translation;
                $productCategory->description_title_i8n = $description_title_i8n;
            }
        }
        if (Arr::has($modelData, 'translations.description')) {
            foreach ($modelData['translations']['description'] as $locale => $translation) {
                $description_i8n[$locale] = $translation;
                $productCategory->description_i8n = $description_i8n;
            }
        }
        if (Arr::has($modelData, 'translations.description_extra')) {
            foreach ($modelData['translations']['description_extra'] as $locale => $translation) {
                $description_extra_i8n[$locale] = $translation;
                $productCategory->description_extra_i8n = $description_extra_i8n;
            }
        }

        $productCategory->save();

        if($productCategory->masterProductCategory) {
            $this->updateMaster($productCategory->masterProductCategory, $name_i8n, $description_i8n, $description_title_i8n, $description_extra_i8n);
        }


        return $productCategory;


    }

    public function updateMaster(MasterProductCategory $masterProductCategory, array $name_i8n, array $description_i8n, array $description_title_i8n, array $description_extra_i8n) 
    {
        $masterNameI8n = $masterProductCategory->getTranslations('name_i8n');
        $masterDescriptionI8n =  $masterProductCategory->getTranslations('description_i8n');
        $masterDescriptionTitleI8n =  $masterProductCategory->getTranslations('description_title_i8n');
        $masterDescriptionExtraI8n = $masterProductCategory->getTranslations('description_extra_i8n');
        
        $updateMaster = false;
        
        if (!empty($name_i8n)) {
            foreach ($name_i8n as $locale => $translation) {
                $masterNameI8n[$locale] = $translation;
            }
            $masterProductCategory->name_i8n = $masterNameI8n;
            $updateMaster = true;
        }
        
        if (!empty($description_i8n)) {
            foreach ($description_i8n as $locale => $translation) {
                $masterDescriptionI8n[$locale] = $translation;
            }
            $masterProductCategory->description_i8n = $masterDescriptionI8n;
            $updateMaster = true;
        }
        
        if (!empty($description_title_i8n)) {
            foreach ($description_title_i8n as $locale => $translation) {
                $masterDescriptionTitleI8n[$locale] = $translation;
            }
            $masterProductCategory->description_title_i8n = $masterDescriptionTitleI8n;
            $updateMaster = true;
        }
        
        if (!empty($description_extra_i8n)) {
            foreach ($description_extra_i8n as $locale => $translation) {
                $masterDescriptionExtraI8n[$locale] = $translation;
            }
            $masterProductCategory->description_extra_i8n = $masterDescriptionExtraI8n;
            $updateMaster = true;
        }
        
        if ($updateMaster) {
            $masterProductCategory->save();
        }
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
