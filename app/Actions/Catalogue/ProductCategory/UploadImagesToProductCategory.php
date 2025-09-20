<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 12 Sept 2025 21:22:11 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\OrgAction;
use App\Actions\Traits\WithAttachMediaToModel;
use App\Actions\Traits\WithUploadModelImages;
use App\Models\Catalogue\ProductCategory;
use Lorisleiva\Actions\ActionRequest;

class UploadImagesToProductCategory extends OrgAction
{
    use WithAttachMediaToModel;
    use WithUploadModelImages;

    public function handle(ProductCategory $model, string $scope, array $modelData, bool $updateDependants = false): array
    {
        $medias = $this->uploadImages($model, $scope, $modelData);
        if ($updateDependants && $model->masterProductCategory) {
            $this->updateDependants($model, $medias, $scope);
        }

        return $medias;
    }

    public function updateDependants(ProductCategory $seedProductCategory, array $medias, string $scope): void
    {
        $masterProductCategory = $seedProductCategory->masterProductCategory;
        foreach ($medias as $media) {
            $this->attachMediaToModel($masterProductCategory, $media, $scope);
        }

        foreach ($masterProductCategory->productCategories as $productCategory) {
            if ($productCategory->id != $seedProductCategory->id) {
                foreach ($medias as $media) {
                    $this->attachMediaToModel($productCategory, $media, $scope);
                }
            }
        }
    }

    public function rules(): array
    {
        return $this->imageUploadRules();
    }

    public function asController(ProductCategory $productCategory, ActionRequest $request): void
    {
        $this->initialisationFromShop($productCategory->shop, $request);

        $this->handle($productCategory, 'image', $this->validatedData, true);
    }
}
