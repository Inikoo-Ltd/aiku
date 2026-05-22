<?php

namespace App\Http\Resources\Catalogue;

use App\Actions\Helpers\Images\GetPictureSources;
use App\Models\Helpers\Media;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewMediaResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Media $media */
        $media = $this;

        return [
            'id'           => $media->id,
            'media_id'     => $media->id,
            'sort_order'   => (int) ($media->order_column ?? 0),
            'media_url'    => GetPictureSources::run($media->getImage()),
            'original_url' => $media->getUrl(),
            'file_name'    => $media->file_name,
            'file_size'    => $media->size,
            'file_mime'    => $media->mime_type,
            'created_at'   => $media->created_at,
            'updated_at'   => $media->updated_at,
        ];
    }
}
