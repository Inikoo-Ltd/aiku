<?php

namespace App\Http\Resources\Catalogue;

use App\Models\Reviews\Review;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewsInIrisResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Review $review */
        $review = $this->resource;

        return [
            'id'                => $review->id,
            'name'              => maskName($review->contact_name),
            'rating'            => $review->rating_main,
            'message'           => $review->message,
            'date'              => $review->published_at,
            'web_images'        => $review->web_images,
            'likes'             => $review->likes,
            'dislikes'          => $review->dislikes,
            'review_reactions'  => $review->review_reaction, // Later
        ];
    }
}
