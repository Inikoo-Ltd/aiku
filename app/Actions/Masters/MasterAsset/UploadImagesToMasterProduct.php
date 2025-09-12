<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 12 Sept 2025 13:09:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\GrpAction;
use App\Actions\Traits\WithUploadModelImages;
use App\Models\Masters\MasterAsset;
use Lorisleiva\Actions\ActionRequest;

class UploadImagesToMasterProduct extends GrpAction
{
    use WithUploadModelImages;

    public function handle(MasterAsset $model, string $scope, array $modelData): array
    {
        return $this->uploadImages($model, $scope, $modelData);
    }

    public function rules(): array
    {
        return $this->imageUploadRules();
    }

    public function asController(MasterAsset $masterAsset, ActionRequest $request): void
    {
        $this->initialisation($masterAsset->group, $request);

        $this->handle($masterAsset, 'image', $this->validatedData);
    }
}
