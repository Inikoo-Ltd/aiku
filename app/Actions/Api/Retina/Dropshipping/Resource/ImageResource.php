<?php

namespace App\Actions\Api\Retina\Dropshipping\Resource;

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

        $image          = $media->getImage();
        $imageThumbnail = $media->getImage()->resize(0, 48);

        return [
            'id'                   => $media->media_id,
            'is_animated'          => $media->is_animated,
            'slug'                 => $media->slug,
            'uuid'                 => $media->uuid,
            'name'                 => $media->name,
            'mime_type'            => $media->mime_type,
            'size'                 => NaturalLanguage::make()->fileSize($media->size),
            'thumbnail'            => GetPictureSources::run($imageThumbnail),
            'source'               => GetPictureSources::run($image),
            'created_at'           => $media->created_at,
            'was_recently_created' => $media->wasRecentlyCreated
        ];
    }
}
