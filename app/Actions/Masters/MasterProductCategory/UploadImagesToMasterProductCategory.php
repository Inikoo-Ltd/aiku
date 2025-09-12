<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 12 Sept 2025 21:22:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory;

use App\Actions\GrpAction;
use App\Actions\Traits\WithAttachMediaToModel;
use App\Actions\Traits\WithUploadModelImages;
use App\Models\Masters\MasterProductCategory;
use Lorisleiva\Actions\ActionRequest;

class UploadImagesToMasterProductCategory extends GrpAction
{
    use WithAttachMediaToModel;
    use WithUploadModelImages;

    public function handle(MasterProductCategory $model, string $scope, array $modelData, bool $updateDependants = false): array
    {
        $medias = $this->uploadImages($model, $scope, $modelData);
        if ($updateDependants) {
            $this->updateDependants($model, $medias, $scope);
        }

        return $medias;
    }

    public function updateDependants(MasterProductCategory $seedMasterProductCategory, array $medias, string $scope): void
    {
        foreach ($seedMasterProductCategory->productCategories as $productCategory) {
            foreach ($medias as $media) {
                $this->attachMediaToModel($productCategory, $media, $scope);
            }
        }
    }

    public function rules(): array
    {
        return $this->imageUploadRules();
    }

    public function asController(MasterProductCategory $masterProductCategory, ActionRequest $request): void
    {
        $this->initialisation($masterProductCategory->group, $request);

        $this->handle($masterProductCategory, 'image', $this->validatedData, true);
    }
}
