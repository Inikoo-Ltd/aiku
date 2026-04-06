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
            'media'      => $this->whenLoaded('media', function () use ($reviewMedia): array {
                return [
                    'id'           => $reviewMedia->media->id,
                    'name'         => $reviewMedia->media->name,
                    'file_name'    => $reviewMedia->media->file_name,
                    'mime_type'    => $reviewMedia->media->mime_type,
                    'size'         => $reviewMedia->media->size,
                    'original_url' => $reviewMedia->media->original_url,
                    'preview_url'  => $reviewMedia->media->preview_url,
                ];
            }),
            'created_at' => $reviewMedia->created_at,
            'updated_at' => $reviewMedia->updated_at,
        ];
    }
}
