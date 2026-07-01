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
            'review_reactions'  => $review->review_reaction,
            'reply_likes'       => $review->replay_likes,
            'reply_dislikes'    => $review->replay_dislikes,
            'reply_reactions'   => $review->reply_reaction,
            'reply'             => $review->reply,
            'reply_by'          => $review->reply_by,
        ];
    }
}
