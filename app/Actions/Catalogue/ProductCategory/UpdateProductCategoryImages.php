<?php

/*
 * author Arya Permana - Kirin
 * created on 08-07-2025-15h-37m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\Concerns\CanUpdateImages;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\ProductCategory;
use Lorisleiva\Actions\ActionRequest;

class UpdateProductCategoryImages extends OrgAction
{
    use WithActionUpdate;
    use CanUpdateImages;

    public function handle(ProductCategory $productCategory, array $modelData): ProductCategory
    {
        $this->updateImages($productCategory, $modelData);

        $this->update($productCategory, $modelData);

        UpdateProductCategoryWebImages::run($productCategory);


        return $productCategory;
    }

    public function rules(): array
    {
        return [
            'image_id' => ['sometimes', 'nullable', 'exists:media,id'],
        ];
    }


    public function asController(ProductCategory $productCategory, ActionRequest $request): void
    {
        $this->initialisationFromShop($productCategory->shop, $request);

        $this->handle($productCategory, $this->validatedData);
    }
}
