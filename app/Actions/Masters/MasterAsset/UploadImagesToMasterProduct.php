<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 12 Sept 2025 13:09:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\GrpAction;
use App\Actions\Traits\WithAttachMediaToModel;
use App\Actions\Traits\WithUploadModelImages;
use App\Models\Masters\MasterAsset;
use Lorisleiva\Actions\ActionRequest;

class UploadImagesToMasterProduct extends GrpAction
{
    use WithUploadModelImages;
    use WithAttachMediaToModel;

    public function handle(MasterAsset $model, string $scope, array $modelData, bool $updateDependants = false): array
    {
        $medias = $this->uploadImages($model, $scope, $modelData);

        if ($updateDependants) {

            if (!$model->is_single_trade_unit || !$model->follow_trade_unit_media ) {
                UpdateMasterProductImages::make()->updateDependants($model);

            }

        }

        return $medias;
    }

    public function rules(): array
    {
        return $this->imageUploadRules();
    }

    public function asController(MasterAsset $masterAsset, ActionRequest $request): void
    {
        $this->initialisation($masterAsset->group, $request);

        $this->handle($masterAsset, 'image', $this->validatedData, true);
    }
}
