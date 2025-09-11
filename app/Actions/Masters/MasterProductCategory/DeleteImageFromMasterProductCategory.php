<?php

namespace App\Actions\Masters\MasterProductCategory;

use App\Actions\GrpAction;
use App\Models\Helpers\Media;
use App\Models\Masters\MasterProductCategory;
use Lorisleiva\Actions\ActionRequest;

class DeleteImageFromMasterProductCategory extends GrpAction
{
    public function handle(MasterProductCategory $masterProductCategory, Media $media): MasterProductCategory
    {
        $masterProductCategory->images()->detach($media->id);

        $imageColumns = [
            'image_id',
        ];

        $updateData = [];

        foreach ($imageColumns as $column) {
            if ($masterProductCategory->{$column} == $media->id) {
                $updateData[$column] = null;
            }
        }

        if (!empty($updateData)) {
            $masterProductCategory->update($updateData);
        }

        return $masterProductCategory;
    }

    public function asController(MasterProductCategory $masterProductCategory, Media $media, ActionRequest $request): void
    {
        $this->initialisation($masterProductCategory->group, $request);

        $this->handle($masterProductCategory, $media);
    }
}
