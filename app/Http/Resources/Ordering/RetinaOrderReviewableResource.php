<?php

namespace App\Http\Resources\Ordering;

use App\Http\Resources\Helpers\ImageResource;
use App\Models\Helpers\Media;
use Illuminate\Http\Resources\Json\JsonResource;

class RetinaOrderReviewableResource extends JsonResource
{
    public function toArray($request): array
    {
        $orderId        = (int) ($this->order_id ?? 0);
        $reviewableId   = (int) ($this->reviewable_id ?? 0);
        $reviewableType = (string) ($this->reviewable_type ?? 'Product');

        $imageData = is_string($this->row_image_data)
            ? json_decode($this->row_image_data, true)
            : (array) ($this->row_image_data ?? []);
        $rowMedia = $imageData ? Media::hydrate([$imageData])->first() : null;

        $reviewMediaData = is_string($this->review_media_data)
            ? json_decode($this->review_media_data, true)
            : ($this->review_media_data ?? []);
        $reviewImages = $reviewMediaData
            ? Media::hydrate($reviewMediaData)->map(fn ($media) => ImageResource::make($media)->getArray())->values()->all()
            : [];

        return [
            'id'               => $reviewableId,
            'image'            => $rowMedia ? ImageResource::make($rowMedia)->getArray() : null,
            'asset_code'       => $this->asset_code,
            'asset_name'       => $this->asset_name,
            'quantity_ordered' => $this->quantity_ordered !== null ? (float) $this->quantity_ordered : null,
            'price'            => $this->price !== null ? (float) $this->price : null,
            'review_rating'    => $this->review_rating !== null ? (float) $this->review_rating : null,
            'review'           => [
                'review_id'       => $this->review_id ? (int) $this->review_id : null,
                'status'          => $this->review_status,
                'rating'          => $this->review_rating !== null ? (float) $this->review_rating : null,
                'rating_a'        => $this->review_rating_a !== null ? (int) $this->review_rating_a : null,
                'rating_b'        => $this->review_rating_b !== null ? (int) $this->review_rating_b : null,
                'rating_c'        => $this->review_rating_c !== null ? (int) $this->review_rating_c : null,
                'rating_d'        => $this->review_rating_d !== null ? (int) $this->review_rating_d : null,
                'rating_e'        => $this->review_rating_e !== null ? (int) $this->review_rating_e : null,
                'message'         => $this->review_message,
                'is_public'       => $this->review_is_public !== null ? (bool) $this->review_is_public : true,
                'review_images'   => $reviewImages,
                'reviewable_type' => $reviewableType,
                'reviewable_id'   => $reviewableId,
                'order_id'        => $orderId,
            ],
        ];
    }
}
