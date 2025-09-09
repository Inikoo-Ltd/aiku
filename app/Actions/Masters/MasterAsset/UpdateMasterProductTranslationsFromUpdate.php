<?php

namespace App\Actions\Masters\MasterAsset;

use App\Actions\GrpAction;
use App\Models\Catalogue\Product;
use App\Models\Masters\MasterAsset;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateMasterProductTranslationsFromUpdate extends GrpAction
{
    use asAction;

    public function handle(MasterAsset $masterAsset, array $modelData): MasterAsset
    {
        $name_i8n = [];
        $description_i8n = [];
        $description_title_i8n = [];
        $description_extra_i8n = [];

        if (Arr::has($modelData, 'translations.name')) {
            foreach ($modelData['translations']['name'] as $locale => $translation) {
                $name_i8n[$locale] = $translation;
                $masterAsset->name_i8n = $name_i8n;
            }
        }
        if (Arr::has($modelData, 'translations.description_title')) {
            foreach ($modelData['translations']['description_title'] as $locale => $translation) {
                $description_title_i8n[$locale] = $translation;
                $masterAsset->description_title_i8n = $description_title_i8n;
            }
        }
        if (Arr::has($modelData, 'translations.description')) {
            foreach ($modelData['translations']['description'] as $locale => $translation) {
                $description_i8n[$locale] = $translation;
                $masterAsset->description_i8n = $description_i8n;
            }
        }
        if (Arr::has($modelData, 'translations.description_extra')) {
            foreach ($modelData['translations']['description_extra'] as $locale => $translation) {
                $description_extra_i8n[$locale] = $translation;
                $masterAsset->description_extra_i8n = $description_extra_i8n;
            }
        }

        $masterAsset->save();

        if ($masterAsset->products) {
            foreach ($masterAsset->products as $product) {
                $this->updateChildren($product, $name_i8n, $description_i8n, $description_title_i8n, $description_extra_i8n);
            }
        }


        return $masterAsset;


    }

    public function updateChildren(Product $product, array $name_i8n, array $description_i8n, array $description_title_i8n, array $description_extra_i8n)
    {
        $childNameI8n = $product->getTranslations('name_i8n');
        $childDescriptionI8n = $product->getTranslations('description_i8n');
        $childDescriptionTitleI8n = $product->getTranslations('description_title_i8n');
        $childDescriptionExtraI8n =  $product->getTranslations('description_extra_i8n');
        $childLanguage = $product->shop->language->code;

        $updateChild = false;
        if (!empty($name_i8n)) {
            foreach ($name_i8n as $locale => $translation) {
                $childNameI8n[$locale] = $translation;
                if ($locale === $childLanguage) {
                    $product->name = $translation;
                }
            }
            $product->name_i8n = $childNameI8n;
            $updateChild = true;
        }

        if (!empty($description_i8n)) {
            foreach ($description_i8n as $locale => $translation) {
                $childDescriptionI8n[$locale] = $translation;
                if ($locale === $childLanguage) {
                    $product->description = $translation;
                }
            }
            $product->description_i8n = $childDescriptionI8n;
            $updateChild = true;
        }

        if (!empty($description_title_i8n)) {
            foreach ($description_title_i8n as $locale => $translation) {
                $childDescriptionTitleI8n[$locale] = $translation;
                if ($locale === $childLanguage) {
                    $product->description_title = $translation;
                }
            }
            $product->description_title_i8n = $childDescriptionTitleI8n;
            $updateChild = true;
        }

        if (!empty($description_extra_i8n)) {
            foreach ($description_extra_i8n as $locale => $translation) {
                $childDescriptionExtraI8n[$locale] = $translation;
                if ($locale === $childLanguage) {
                    $product->description_extra = $translation;
                }
            }
            $product->description_extra_i8n = $childDescriptionExtraI8n;
            $updateChild = true;
        }

        if ($updateChild) {
            $product->save();
        }
    }

    public function rules(): array
    {
        return [
            'translations' => ['required', 'array'],
        ];
    }

    public function action(MasterAsset $masterAsset, array $modelData): void
    {
        $this->initialisation($masterAsset->group, $modelData);
        $this->handle($masterAsset, $this->validatedData);
    }

}
