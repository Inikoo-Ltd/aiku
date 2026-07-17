<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Jul 2026 15:35:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Catalogue;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $language_id
 * @property mixed $contact_name
 * @property mixed $id
 * @property mixed $rating_main
 * @property mixed|string $location
 * @property mixed $translations
 * @property mixed $published_at
 * @property mixed $web_images
 * @property mixed $message
 * @property mixed $likes
 * @property mixed $reply_by
 * @property mixed $dislikes
 * @property mixed $review_reaction
 * @property mixed $replay_likes
 * @property mixed $replay_dislikes
 * @property mixed $reply_reaction
 * @property mixed $reply
 */
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
            'message_translated'    => data_get($this->translations, "message.$languageId"),
            'date'                  => $this->published_at,
            'web_images'            => $this->web_images,
            'likes'                 => $this->likes,
            'dislikes'              => $this->dislikes,
            'review_reactions'      => $this->review_reaction,
            'reply_likes'           => $this->replay_likes,
            'reply_dislikes'        => $this->replay_dislikes,
            'reply_reactions'       => $this->reply_reaction,
            'reply'                 => $this->reply,
            'reply_translated'      => data_get($this->translations, "reply_message.$languageId"),
            'reply_by'              => $this->reply_by,
        ];
    }
}
