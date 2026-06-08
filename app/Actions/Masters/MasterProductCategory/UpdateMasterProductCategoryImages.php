<?php

/*
 * author Arya Permana - Kirin
 * created on 08-07-2025-15h-37m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Masters\MasterProductCategory;

use App\Actions\Catalogue\ProductCategory\UpdateProductCategoryImages;
use App\Actions\Catalogue\WithUpdateWebImages;
use App\Actions\Concerns\CanUpdateImages;
use App\Actions\GrpAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateMasterProductCategoryImages extends GrpAction
{
    use WithActionUpdate;
    use WithUpdateWebImages;
    use CanUpdateImages;

    public function handle(MasterProductCategory $masterProductCategory, array $modelData, bool $updateDependants = false): MasterProductCategory
    {
        $this->updateImages($masterProductCategory, $modelData);

        $this->update($masterProductCategory, $modelData);

        if (Arr::hasAny($modelData, [
            'showcase_image_id',
            'desc_art1',
            'desc_art2',
            'desc_art3',
            'desc_art4',
            'desc_art5',
            'extra_desc_art1',
            'extra_desc_art2',
            'extra_desc_art3',
            'extra_desc_art4',
        ])) {
            $this->updateWebImages($masterProductCategory);
        }

        if ($updateDependants) {
            $this->updateDependants($masterProductCategory, $modelData);
        }

        return $masterProductCategory;
    }

    public function updateDependants(MasterProductCategory $seedMasterProductCategory, array $modelData): void
    {
        foreach ($seedMasterProductCategory->productCategories as $productCategory) {
            UpdateProductCategoryImages::run($productCategory, $modelData);
        }
    }

    public function rules(): array
    {
        return [
            'image_id'          => ['sometimes', 'nullable', 'exists:media,id'],
            'showcase_image_id' => ['sometimes', 'nullable', 'exists:media,id'],
            'desc_art1'         => ['sometimes', 'nullable', 'exists:media,id'],
            'desc_art2'         => ['sometimes', 'nullable', 'exists:media,id'],
            'desc_art3'         => ['sometimes', 'nullable', 'exists:media,id'],
            'desc_art4'         => ['sometimes', 'nullable', 'exists:media,id'],
            'desc_art5'         => ['sometimes', 'nullable', 'exists:media,id'],
            'extra_desc_art1'   => ['sometimes', 'nullable', 'exists:media,id'],
            'extra_desc_art2'   => ['sometimes', 'nullable', 'exists:media,id'],
            'extra_desc_art3'   => ['sometimes', 'nullable', 'exists:media,id'],
            'extra_desc_art4'   => ['sometimes', 'nullable', 'exists:media,id'],
            'desc_video_url'    => ['sometimes', 'nullable'],
        ];
    }


    public function asController(MasterProductCategory $masterProductCategory, ActionRequest $request): void
    {
        $this->initialisation($masterProductCategory->group, $request);

        $this->handle($masterProductCategory, $this->validatedData, true);
    }
}
