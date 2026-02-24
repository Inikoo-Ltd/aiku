<?php

/*
 * author Arya Permana - Kirin
 * created on 08-07-2025-15h-37m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Masters\MasterProductCategory;

use App\Actions\Catalogue\ProductCategory\UpdateProductCategoryImages;
use App\Actions\Concerns\CanUpdateImages;
use App\Actions\GrpAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Masters\MasterProductCategory;
use Lorisleiva\Actions\ActionRequest;

class UpdateMasterProductCategoryImages extends GrpAction
{
    use WithActionUpdate;
    use CanUpdateImages;

    public function handle(MasterProductCategory $masterProductCategory, array $modelData, bool $updateDependants = false): MasterProductCategory
    {
        $this->updateImages($masterProductCategory, $modelData);

        $this->update($masterProductCategory, $modelData);

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
            'image_id' => ['sometimes', 'nullable', 'exists:media,id'],
        ];
    }


    public function asController(MasterProductCategory $masterProductCategory, ActionRequest $request): void
    {
        $this->initialisation($masterProductCategory->group, $request);

        $this->handle($masterProductCategory, $this->validatedData, true);
    }
}
