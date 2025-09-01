<?php

namespace App\Actions\Catalogue\Product;

use App\Actions\OrgAction;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateProductTranslations extends OrgAction
{
    use asAction;

    public function handle(Product $product, array $modelData): Product
    {
        $name_i8n = [];
        $description_i8n = [];
        $description_title_i8n = [];
        $description_extra_i8n = [];
        
        if(Arr::has($modelData, 'translations.name')) {
            foreach ($modelData['translations']['name'] as $locale => $translation) {
                $name_i8n[$locale] = $translation;
                $product->name_i8n = $name_i8n;
            }
        }
        if(Arr::has($modelData, 'translations.description_title')) {
            foreach ($modelData['translations']['description_title'] as $locale => $translation) {
                $description_title_i8n[$locale] = $translation;
                $product->description_title_i8n = $description_title_i8n;
            }
        }
        if(Arr::has($modelData, 'translations.description')) {
            foreach ($modelData['translations']['description'] as $locale => $translation) {
                $description_i8n[$locale] = $translation;
                $product->description_i8n = $description_i8n;
            }
        }
        if(Arr::has($modelData, 'translations.description_extra')) {
            foreach ($modelData['translations']['description_extra'] as $locale => $translation) {
                $description_extra_i8n[$locale] = $translation;
                $product->description_extra_i8n = $description_extra_i8n;
            }
        }

        $product->save();


        return $product;


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
