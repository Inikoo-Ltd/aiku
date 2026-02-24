<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Feb 2026 17:55:44 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Concerns;

use App\Models\Catalogue\Collection;
use App\Models\Catalogue\ProductCategory;
use App\Models\Helpers\Media;
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

trait CanUpdateImages
{
    protected function updateImages(Collection|MasterCollection|MasterProductCategory|ProductCategory|Model $model, array $modelData, array $imageTypeMapping = ['image_id' => 'main']): void
    {
        $imageKeys = collect($imageTypeMapping)
            ->keys()
            ->filter(fn ($key) => Arr::exists($modelData, $key))
            ->toArray();

        foreach ($imageKeys as $imageKey) {
            $mediaId = $modelData[$imageKey];

            if ($mediaId === null) {
                $model->images()->wherePivot('sub_scope', $imageTypeMapping[$imageKey])
                    ->updateExistingPivot(
                        $model->images()
                            ->wherePivot('sub_scope', $imageTypeMapping[$imageKey])
                            ->first()?->id,
                        ['sub_scope' => null]
                    );
            } else {
                $media = Media::find($mediaId);

                if ($media) {
                    $model->images()
                        ->wherePivot('sub_scope', $imageTypeMapping[$imageKey])
                        ->updateExistingPivot(
                            $model->images()
                                ->wherePivot('sub_scope', $imageTypeMapping[$imageKey])
                                ->first()?->id,
                            ['sub_scope' => null]
                        );

                    $model->images()->updateExistingPivot(
                        $media->id,
                        ['sub_scope' => $imageTypeMapping[$imageKey]]
                    );
                }
            }
        }
    }
}
