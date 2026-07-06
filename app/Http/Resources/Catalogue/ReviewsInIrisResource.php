<?php

namespace App\Http\Resources\Catalogue;

use App\Models\Reviews\Review;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewsInIrisResource extends JsonResource
{
    public function toArray($request): array
    {
        
        $languageId = $this->language_id;

        return [
            'id'                    => $this->id,
            'name'                  => maskName($this->contact_name),
            'customer_location'     => is_string($this->location) ? json_decode($this->location, true) : $this->location,
            'rating'                => $this->rating_main,
            'message'               => $this->message,
            'message_translated'    => data_get($this->translations, "message.$languageId", null),
            'date'                  => $this->published_at,
            'web_images'            => $this->web_images,
            'likes'                 => $this->likes,
            'dislikes'              => $this->dislikes,
            'review_reactions'      => $this->review_reaction,
            'reply_likes'           => $this->replay_likes,
            'reply_dislikes'        => $this->replay_dislikes,
            'reply_reactions'       => $this->reply_reaction,
            'reply'                 => $this->reply,
            'reply_translated'      => data_get($this->translations, "reply_message.$languageId", null),
            'reply_by'              => $this->reply_by,
        ];
    }
}
