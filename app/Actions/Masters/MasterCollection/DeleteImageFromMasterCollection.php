<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 25 Nov 2025 14:00:00 UTC
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterCollection;

use App\Actions\GrpAction;
use App\Models\Helpers\Media;
use App\Models\Masters\MasterCollection;
use Lorisleiva\Actions\ActionRequest;

class DeleteImageFromMasterCollection extends GrpAction
{
    public function handle(MasterCollection $masterCollection, Media $media, bool $updateDependants = false): MasterCollection
    {
        $masterCollection->images()->detach($media->id);

        $imageColumns = [
            'image_id',
        ];

        $updateData = [];

        foreach ($imageColumns as $column) {
            if ($masterCollection->{$column} == $media->id) {
                $updateData[$column] = null;
            }
        }

        if (!empty($updateData)) {
            $masterCollection->update($updateData);
        }

        if ($updateDependants) {
            $this->updateDependants($masterCollection, $media);
        }

        return $masterCollection;
    }

    public function updateDependants(MasterCollection $seedMasterCollection, Media $media): void
    {
        // Master Collections don't have dependants to update
        // This method is kept for consistency with the trait interface
    }

    public function asController(MasterCollection $masterCollection, Media $media, ActionRequest $request): void
    {
        $this->initialisation($masterCollection->group, $request);

        $this->handle($masterCollection, $media, false);
    }
}
