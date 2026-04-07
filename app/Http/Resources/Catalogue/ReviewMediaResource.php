<?php

namespace App\Http\Resources\Catalogue;

use App\Models\Catalogue\ReviewMedia;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewMediaResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var ReviewMedia $reviewMedia */
        $reviewMedia = $this;

        return [
            'id'         => $reviewMedia->id,
            'review_id'  => $reviewMedia->review_id,
            'media_id'   => $reviewMedia->media_id,
            'type'       => $reviewMedia->type?->value ?? $reviewMedia->type,
            'sort_order' => (int) $reviewMedia->sort_order,
            'meta'       => $reviewMedia->meta ?? [],
            'media_url'  => $reviewMedia->imageSources(0, 0, 'media'),
            'original_url' => $reviewMedia->media?->getUrl(),
            'file_name'  => $reviewMedia->media?->file_name,
            'file_size'  => $reviewMedia->media?->size,
            'file_mime'  => $reviewMedia->media?->mime_type,
            'created_at' => $reviewMedia->created_at,
            'updated_at' => $reviewMedia->updated_at,
        ];
    }
}
