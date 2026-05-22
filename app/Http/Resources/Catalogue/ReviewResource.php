<?php

namespace App\Http\Resources\Catalogue;

use App\Models\Catalogue\Review;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Review $review */
        $review = $this;

        return [
            'id'                   => $review->id,
            'group_id'             => $review->group_id,
            'organisation_id'      => $review->organisation_id,
            'shop_id'              => $review->shop_id,
            'customer_id'          => $review->customer_id,
            'reviewable_type'      => class_basename($review->reviewable_type),
            'reviewable_id'        => $review->reviewable_id,
            'status'               => $review->status?->value ?? $review->status,
            'rating'               => (int) $review->rating,
            'message'              => $review->message,
            'like_count'           => (int) $review->like_count,
            'meta'                 => $review->meta ?? [],
            'customer'             => $this->whenLoaded('customer', function () use ($review): array {
                return [
                    'id'   => $review->customer->id,
                    'name' => $review->customer->name,
                    'slug' => $review->customer->slug,
                ];
            }),
            'media'                => ReviewMediaResource::collection($this->whenLoaded('media')),
            'created_at'           => $review->created_at,
            'updated_at'           => $review->updated_at,
            'deleted_at'           => $review->deleted_at,
        ];
    }
}
