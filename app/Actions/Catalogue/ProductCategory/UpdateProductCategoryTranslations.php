<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 05 Aug 2025 11:42:45 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\OrgAction;
use App\Models\Catalogue\ProductCategory;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateProductCategoryTranslations extends OrgAction
{
    use asAction;

    public function handle(ProductCategory $productCategory, array $modelData): ProductCategory
    {
        UpdateProductCategory::make()->action($productCategory, $modelData['master']);


        $name_i8n = [];
        $description_i8n = [];
        $description_title_i8n = [];
        $description_extra_i8n = [];

        foreach ($modelData['translations'] as $locale => $translation) {
            $name_i8n[$locale] = $translation['name'];
            $description_i8n[$locale] = $translation['description'];
            $description_title_i8n[$locale] = $translation['description_title'];
            $description_extra_i8n[$locale] = $translation['description_extra'];
        }
        $productCategory->name_i8n = $name_i8n;
        $productCategory->description_i8n = $description_i8n;
        $productCategory->description_title_i8n = $description_title_i8n;
        $productCategory->description_extra_i8n = $description_extra_i8n;
        $productCategory->save();


        return $productCategory;


    }

    public function rules(): array
    {
        return [
            'master' => ['required', 'array'],
            'master.name' => 'required|string',
            'master.description' => ['present','nullable','string','max:10000'],
            'master.description_title' => ['present','nullable','string','max:1000'],
            'master.description_extra' => ['present','nullable','string','max:20000'],
            'translations' => ['required', 'array'],
        ];
    }

    public function asController(ProductCategory $productCategory, ActionRequest $request): void
    {
        $this->initialisationFromGroup(group(), $request);
        $this->handle($productCategory, $this->validatedData);
    }

}
