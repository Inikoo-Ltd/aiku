<?php

namespace App\Actions\Catalogue\Product;

use App\Actions\OrgAction;
use App\Models\Catalogue\Product;
use App\Models\Masters\MasterAsset;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateProductAndMasterTranslations extends OrgAction
{
    use asAction;

    public function handle(Product $product, array $modelData): Product
    {
        $name_i8n = [];
        $description_i8n = [];
        $description_title_i8n = [];
        $description_extra_i8n = [];

        if (Arr::has($modelData, 'translations.name')) {
            foreach ($modelData['translations']['name'] as $locale => $translation) {
                $name_i8n[$locale] = $translation;
                $product->name_i8n = $name_i8n;
            }
        }
        if (Arr::has($modelData, 'translations.description_title')) {
            foreach ($modelData['translations']['description_title'] as $locale => $translation) {
                $description_title_i8n[$locale] = $translation;
                $product->description_title_i8n = $description_title_i8n;
            }
        }
        if (Arr::has($modelData, 'translations.description')) {
            foreach ($modelData['translations']['description'] as $locale => $translation) {
                $description_i8n[$locale] = $translation;
                $product->description_i8n = $description_i8n;
            }
        }
        if (Arr::has($modelData, 'translations.description_extra')) {
            foreach ($modelData['translations']['description_extra'] as $locale => $translation) {
                $description_extra_i8n[$locale] = $translation;
                $product->description_extra_i8n = $description_extra_i8n;
            }
        }

        $product->save();

        if ($product->masterProduct) {
            $this->updateMaster($product->masterProduct, $name_i8n, $description_i8n, $description_title_i8n, $description_extra_i8n);
        }


        return $product;


    }

    public function updateMaster(MasterAsset $masterProduct, array $name_i8n, array $description_i8n, array $description_title_i8n, array $description_extra_i8n)
    {
        $masterNameI8n = $masterProduct->getTranslations('name_i8n');
        $masterDescriptionI8n =  $masterProduct->getTranslations('description_i8n');
        $masterDescriptionTitleI8n =  $masterProduct->getTranslations('description_title_i8n');
        $masterDescriptionExtraI8n = $masterProduct->getTranslations('description_extra_i8n');

        $updateMaster = false;

        if (!empty($name_i8n)) {
            foreach ($name_i8n as $locale => $translation) {
                $masterNameI8n[$locale] = $translation;
            }
            $masterProduct->name_i8n = $masterNameI8n;
            $updateMaster = true;
        }

        if (!empty($description_i8n)) {
            foreach ($description_i8n as $locale => $translation) {
                $masterDescriptionI8n[$locale] = $translation;
            }
            $masterProduct->description_i8n = $masterDescriptionI8n;
            $updateMaster = true;
        }

        if (!empty($description_title_i8n)) {
            foreach ($description_title_i8n as $locale => $translation) {
                $masterDescriptionTitleI8n[$locale] = $translation;
            }
            $masterProduct->description_title_i8n = $masterDescriptionTitleI8n;
            $updateMaster = true;
        }

        if (!empty($description_extra_i8n)) {
            foreach ($description_extra_i8n as $locale => $translation) {
                $masterDescriptionExtraI8n[$locale] = $translation;
            }
            $masterProduct->description_extra_i8n = $masterDescriptionExtraI8n;
            $updateMaster = true;
        }

        if ($updateMaster) {
            $masterProduct->save();
        }
    }

    public function rules(): array
    {
        return [
            'translations' => ['required', 'array'],
        ];
    }

    public function action(Product $product, array $modelData): void
    {
        $this->initialisationFromShop($product->shop, $modelData);
        $this->handle($product, $this->validatedData);
    }

}
