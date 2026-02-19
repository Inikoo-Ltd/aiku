<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 12 Sept 2025 20:31:55 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\GrpAction;
use App\Actions\Traits\WithImageColumns;
use App\Models\Helpers\Media;
use App\Models\Masters\MasterAsset;
use Lorisleiva\Actions\ActionRequest;

class DeleteImageFromMasterProduct extends GrpAction
{
    use WithImageColumns;

    public function handle(MasterAsset $masterAsset, Media $media, bool $updateDependants = false): MasterAsset
    {

        if (!$masterAsset->is_single_trade_unit || !$masterAsset->follow_trade_unit_media ) {
            $masterAsset->images()->detach($media->id);

            $updateData = [];

            foreach ($this->imageColumns() as $column) {
                if ($masterAsset->{$column} == $media->id) {
                    $updateData[$column] = null;
                }
            }

            if (!empty($updateData)) {
                $masterAsset->update($updateData);
            }

            if ($updateDependants) {
                UpdateMasterProductImages::make()->updateDependants($masterAsset);
            }
        }
        return $masterAsset;

    }


    public function asController(MasterAsset $masterAsset, Media $media, ActionRequest $request): void
    {
        $this->initialisation($masterAsset->group, $request);

        $this->handle($masterAsset, $media, true);
    }
}
