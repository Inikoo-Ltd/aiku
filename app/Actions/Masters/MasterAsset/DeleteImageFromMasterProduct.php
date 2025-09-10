<?php

namespace App\Actions\Masters\MasterAsset;

use App\Actions\GrpAction;
use App\Models\Helpers\Media;
use App\Models\Masters\MasterAsset;
use Lorisleiva\Actions\ActionRequest;

class DeleteImageFromMasterProduct extends GrpAction
{
    public function handle(MasterAsset $masterAsset, Media $media): MasterAsset
    {
        $masterAsset->images()->detach($media->id);

        $imageColumns = [
            'image_id',
            'front_image_id',
            '34_image_id',
            'right_image_id',
            'back_image_id',
            'bottom_image_id',
            'size_comparison_image_id',
        ];

        $updateData = [];

        foreach ($imageColumns as $column) {
            if ($masterAsset->{$column} == $media->id) {
                $updateData[$column] = null;
            }
        }

        if (!empty($updateData)) {
            $masterAsset->update($updateData);
        }

        return $masterAsset;
    }

    public function asController(MasterAsset $masterAsset, Media $media, ActionRequest $request): void
    {
        $this->initialisation($masterAsset->group, $request);

        $this->handle($masterAsset, $media);
    }
}
