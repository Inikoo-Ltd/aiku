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
use Illuminate\Support\Arr;
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
        
        $changes = Arr::except($collection->getChanges(), ['updated_at']);

        if (Arr::has($changes, 'image_id')) {
            UpdateCollectionWebImages::run($collection);
        }


        return $collection;
    }


    public function asController(Collection $collection, Media $media, ActionRequest $request): void
    {
        $this->initialisationFromShop($collection->shop, $request);

        $this->handle($collection, $media);
    }
}
