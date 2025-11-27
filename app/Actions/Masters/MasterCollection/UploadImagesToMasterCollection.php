<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 07:51:01 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterCollection;

use App\Actions\GrpAction;
use App\Models\Masters\MasterCollection;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\Traits\WithAttachMediaToModel;
use App\Actions\Traits\WithUploadModelImages;

class UploadImagesToMasterCollection extends GrpAction
{
    use WithAttachMediaToModel;
    use WithUploadModelImages;

    public function handle(MasterCollection $model, string $scope, array $modelData, bool $updateDependants = false): array
    {
        $medias = $this->uploadImages($model, $scope, $modelData);
        if ($updateDependants) {
            $this->updateDependants($model, $medias, $scope);
        }

        return $medias;
    }

    public function updateDependants(MasterCollection $seedMasterCollection, array $medias, string $scope): void
    {
        foreach ($seedMasterCollection->childrenCollections as $collection) {
            foreach ($medias as $media) {
                $this->attachMediaToModel($collection, $media, $scope);
            }
        }
    }

    public function rules(): array
    {
        return $this->imageUploadRules();
    }

    public function asController(MasterCollection $masterCollection, ActionRequest $request): void
    {
        $this->initialisation($masterCollection->group, $request);

        $this->handle($masterCollection, 'image', $this->validatedData, true);
    }
}
