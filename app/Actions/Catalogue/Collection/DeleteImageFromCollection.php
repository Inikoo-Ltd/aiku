<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 25 Nov 2025 14:00:00 UTC
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Collection;

use App\Actions\OrgAction;
use App\Models\Catalogue\Collection;
use App\Models\Helpers\Media;
use Lorisleiva\Actions\ActionRequest;

class DeleteImageFromCollection extends OrgAction
{
    public function handle(Collection $collection, Media $media): Collection
    {

        $collection->images()->detach($media->id);

        $imageColumns = [
            'image_id',
        ];

        $updateData = [];

        foreach ($imageColumns as $column) {
            if ($collection->{$column} == $media->id) {
                $updateData[$column] = null;
            }
        }

        if (!empty($updateData)) {
            $collection->update($updateData);
        }


        return $collection;
    }


    public function asController(Collection $collection, Media $media, ActionRequest $request): void
    {
        $this->initialisationFromShop($collection->shop, $request);

        $this->handle($collection, $media);
    }
}
