<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 09 Aug 2023 10:44:37 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Helpers;

use App\Actions\Helpers\Images\GetPictureSources;
use App\Helpers\NaturalLanguage;
use App\Http\Resources\HasSelfCall;
use App\Models\Helpers\Media;
use Illuminate\Http\Resources\Json\JsonResource;

class ImageResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var Media $media */
        $media = $this;

        $image = $media->getImage();
        $imageThumbnail = $media->getImage()->resize(0, 48);

        return [
            'id' => $media->id,
            'is_animated' => $media->is_animated,
            'slug' => $media->slug,
            'uuid' => $media->uuid,
            'name' => $media->name,
            'mime_type' => $media->mime_type,
            'size' => NaturalLanguage::make()->fileSize($media->size),
            'thumbnail' => GetPictureSources::run($imageThumbnail),
            'source' => GetPictureSources::run($image),
            'created_at' => $media->created_at,
            'was_recently_created' => $media->wasRecentlyCreated,
        ];
    }
}
