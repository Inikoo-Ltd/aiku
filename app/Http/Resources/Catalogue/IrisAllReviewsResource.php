<?php

/*
 * @Author: andiferdiawan (https://github.com/andiferdiawan)
 * @Copyright: Copyright (c) 2026, andiferdiawan
 */

namespace App\Http\Resources\Catalogue;

use App\Enums\Catalogue\Review\ReviewReactionTypeEnum;
use App\Enums\Catalogue\Review\ReviewScopeEnum;
use Illuminate\Http\Resources\Json\JsonResource;

class IrisAllReviewsResource extends JsonResource
{
    public function toArray($request): array
    {
        $scope = $this->scope instanceof ReviewScopeEnum ? $this->scope->value : (string) $this->scope;

        $code = match ($scope) {
            'product' => $this->product_code ?? null,
            'family'  => $this->family_code ?? null,
            default   => null,
        };

        $slug = match ($scope) {
            'product' => $this->product_slug ?? null,
            'family'  => $this->family_slug ?? null,
            default   => null,
        };

        return [
            'review_id'       => (int) $this->id,
            'scope'           => $scope,
            'name'            => maskName($this->contact_name),
            'code'            => $code,
            'slug'            => $slug,
            'location'        => is_string($this->location) ? json_decode($this->location, true) : $this->location,
            'created_at'      => $this->published_at,
            'review'          => [
                'review_id'     => (int) $this->id,
                'rating'        => $this->rating_main !== null ? (float) $this->rating_main : null,
                'message'       => $this->message,
                'is_public'     => (bool) $this->is_public,
                'review_images' => $this->web_images ?? [],
                'likes'         => (int) ($this->likes ?? 0),
                'dislikes'      => (int) ($this->dislikes ?? 0),
                'reply'         => $this->reply_message ? [
                    'message'      => $this->reply_message,
                    'at'           => $this->reply_at,
                    'contact_name' => $this->reply_by,
                    'likes'        => (int) ($this->replay_likes ?? 0),
                    'dislikes'     => (int) ($this->replay_dislikes ?? 0),
                ] : null,
            ],
            'review_reaction' => ReviewReactionTypeEnum::getValue($this->review_reaction),
            'reply_reaction'  => ReviewReactionTypeEnum::getValue($this->reply_reaction),
        ];
    }
}
