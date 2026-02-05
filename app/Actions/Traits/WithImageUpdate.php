<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Feb 2026 01:10:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Models\Helpers\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

trait WithImageUpdate
{
    protected function imageTypeMapping(): array
    {
        return [
            'image_id' => 'main',
            'front_image_id' => 'front',
            '34_image_id' => '34',
            'left_image_id' => 'left',
            'right_image_id' => 'right',
            'back_image_id' => 'back',
            'top_image_id' => 'top',
            'bottom_image_id' => 'bottom',
            'size_comparison_image_id' => 'size_comparison',
            'lifestyle_image_id' => 'lifestyle',
            'art1_image_id' => 'art1',
            'art2_image_id' => 'art2',
            'art3_image_id' => 'art3',
            'art4_image_id' => 'art4',
            'art5_image_id' => 'art5',
        ];
    }

    protected function updateModelImages(Model $model, array $modelData): void
    {
        $mapping = $this->imageTypeMapping();

        $imageKeys = collect($mapping)
            ->keys()
            ->filter(fn ($key) => Arr::exists($modelData, $key))
            ->toArray();

        foreach ($imageKeys as $imageKey) {
            $mediaId = $modelData[$imageKey];

            if ($mediaId === null) {
                $model->images()->wherePivot('sub_scope', $mapping[$imageKey])
                    ->updateExistingPivot(
                        $model->images()
                            ->wherePivot('sub_scope', $mapping[$imageKey])
                            ->first()?->id,
                        ['sub_scope' => null]
                    );
            } else {
                $media = Media::find($mediaId);

                if ($media) {
                    $model->images()->updateExistingPivot(
                        $media->id,
                        ['sub_scope' => $mapping[$imageKey]]
                    );
                }
            }
        }
    }

    protected function imageUpdateRules(): array
    {
        return [
            'image_id' => ['sometimes', 'nullable', 'exists:media,id'],
            'front_image_id' => ['sometimes', 'nullable', 'exists:media,id'],
            '34_image_id' => ['sometimes', 'nullable', 'exists:media,id'],
            'left_image_id' => ['sometimes', 'nullable', 'exists:media,id'],
            'right_image_id' => ['sometimes', 'nullable', 'exists:media,id'],
            'back_image_id' => ['sometimes', 'nullable', 'exists:media,id'],
            'top_image_id' => ['sometimes', 'nullable', 'exists:media,id'],
            'bottom_image_id' => ['sometimes', 'nullable', 'exists:media,id'],
            'size_comparison_image_id' => ['sometimes', 'nullable', 'exists:media,id'],
            'lifestyle_image_id' => ['sometimes', 'nullable', 'exists:media,id'],
            'art1_image_id' => ['sometimes', 'nullable', 'exists:media,id'],
            'art2_image_id' => ['sometimes', 'nullable', 'exists:media,id'],
            'art3_image_id' => ['sometimes', 'nullable', 'exists:media,id'],
            'art4_image_id' => ['sometimes', 'nullable', 'exists:media,id'],
            'art5_image_id' => ['sometimes', 'nullable', 'exists:media,id'],
            'video_url' => ['sometimes', 'nullable'],
        ];
    }
}
