<?php

namespace App\Actions\Masters\MasterProductCategory;

use App\Actions\GrpAction;
use App\Models\Catalogue\ProductCategory;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateMasterProductCategoryTranslationsFromUpdate extends GrpAction
{
    use asAction;

    public function handle(MasterProductCategory $masterProductCategory, array $modelData): MasterProductCategory
    {
        $name_i8n = [];
        $description_i8n = [];
        $description_title_i8n = [];
        $description_extra_i8n = [];

        if (Arr::has($modelData, 'translations.name')) {
            foreach ($modelData['translations']['name'] as $locale => $translation) {
                $name_i8n[$locale] = $translation;
                $masterProductCategory->name_i8n = $name_i8n;
            }
        }
        if (Arr::has($modelData, 'translations.description_title')) {
            foreach ($modelData['translations']['description_title'] as $locale => $translation) {
                $description_title_i8n[$locale] = $translation;
                $masterProductCategory->description_title_i8n = $description_title_i8n;
            }
        }
        if (Arr::has($modelData, 'translations.description')) {
            foreach ($modelData['translations']['description'] as $locale => $translation) {
                $description_i8n[$locale] = $translation;
                $masterProductCategory->description_i8n = $description_i8n;
            }
        }
        if (Arr::has($modelData, 'translations.description_extra')) {
            foreach ($modelData['translations']['description_extra'] as $locale => $translation) {
                $description_extra_i8n[$locale] = $translation;
                $masterProductCategory->description_extra_i8n = $description_extra_i8n;
            }
        }

        $masterProductCategory->save();

        if ($masterProductCategory->productCategories) {
            foreach ($masterProductCategory->productCategories as $productCategory) {
                $this->updateChildren($productCategory, $name_i8n, $description_i8n, $description_title_i8n, $description_extra_i8n);
            }
        }


        return $masterProductCategory;


    }

    public function updateChildren(ProductCategory $productCategory, array $name_i8n, array $description_i8n, array $description_title_i8n, array $description_extra_i8n)
    {
        $childNameI8n = $productCategory->getTranslations('name_i8n');
        $childDescriptionI8n = $productCategory->getTranslations('description_i8n');
        $childDescriptionTitleI8n = $productCategory->getTranslations('description_title_i8n');
        $childDescriptionExtraI8n =  $productCategory->getTranslations('description_extra_i8n');
        $childLanguage = $productCategory->shop->language->code;

        $updateChild = false;
        if (!empty($name_i8n)) {
            foreach ($name_i8n as $locale => $translation) {
                $childNameI8n[$locale] = $translation;
                if($locale === $childLanguage) {
                    $productCategory->name = $translation;
                }
            }
            $productCategory->name_i8n = $childNameI8n;
            $updateChild = true;
        }

        if (!empty($description_i8n)) {
            foreach ($description_i8n as $locale => $translation) {
                $childDescriptionI8n[$locale] = $translation;
                if($locale === $childLanguage) {
                    $productCategory->description = $translation;
                }
            }
            $productCategory->description_i8n = $childDescriptionI8n;
            $updateChild = true;
        }

        if (!empty($description_title_i8n)) {
            foreach ($description_title_i8n as $locale => $translation) {
                $childDescriptionTitleI8n[$locale] = $translation;
                if($locale === $childLanguage) {
                    $productCategory->description_title = $translation;
                }
            }
            $productCategory->description_title_i8n = $childDescriptionTitleI8n;
            $updateChild = true;
        }

        if (!empty($description_extra_i8n)) {
            foreach ($description_extra_i8n as $locale => $translation) {
                $childDescriptionExtraI8n[$locale] = $translation;
                if($locale === $childLanguage) {
                    $productCategory->description_extra = $translation;
                }
            }
            $productCategory->description_extra_i8n = $childDescriptionExtraI8n;
            $updateChild = true;
        }

        if ($updateChild) {
            $productCategory->save();
        }
    }

    public function rules(): array
    {
        return [
            'translations' => ['required', 'array'],
        ];
    }

    public function action(MasterProductCategory $masterProductCategory, array $modelData): void
    {
        $this->initialisation($masterProductCategory->group, $modelData);
        $this->handle($masterProductCategory, $this->validatedData);
    }

}
