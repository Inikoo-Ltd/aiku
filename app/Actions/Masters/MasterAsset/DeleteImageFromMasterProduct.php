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

        return $masterAsset;
    }

    public function asController(MasterAsset $masterAsset, Media $media, ActionRequest $request): void
    {
        $this->initialisation($masterAsset->group, $request);

        $this->handle($masterAsset, $media);
    }
}
