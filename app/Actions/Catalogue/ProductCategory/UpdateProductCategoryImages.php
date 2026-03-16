<?php

/*
 * author Arya Permana - Kirin
 * created on 08-07-2025-15h-37m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\Catalogue\WithUpdateWebImages;
use App\Actions\Concerns\CanUpdateImages;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateProductCategoryImages extends OrgAction
{
    use WithActionUpdate;
    use WithUpdateWebImages;
    use CanUpdateImages;

    public function handle(ProductCategory $productCategory, array $modelData): ProductCategory
    {
        $this->updateImages($productCategory, $modelData);

        $this->update($productCategory, $modelData);

        if (Arr::has($modelData, 'extra_desc_art1')) {
            $this->updateWebImages($productCategory);
        }

        UpdateProductCategoryWebImages::run($productCategory);


        return $productCategory;
    }

    public function rules(): array
    {
        return [
            'image_id' => ['sometimes', 'nullable', 'exists:media,id'],
            'desc_art1' => ['sometimes', 'nullable', 'exists:media,id'],
            'desc_art2' => ['sometimes', 'nullable', 'exists:media,id'],
            'desc_art3' => ['sometimes', 'nullable', 'exists:media,id'],
            'desc_art4' => ['sometimes', 'nullable', 'exists:media,id'],
            'desc_art5' => ['sometimes', 'nullable', 'exists:media,id'],
            'extra_desc_art1' => ['sometimes', 'nullable', 'exists:media,id'],
            'desc_video_url' => ['sometimes', 'nullable'],
        ];
    }


    public function asController(ProductCategory $productCategory, ActionRequest $request): void
    {
        $this->initialisationFromShop($productCategory->shop, $request);

        $this->handle($productCategory, $this->validatedData);
    }
}
