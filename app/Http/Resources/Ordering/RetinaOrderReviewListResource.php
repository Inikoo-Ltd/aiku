<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 Jun 2026 19:52:53 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Ordering;

use App\Enums\Catalogue\Review\ReviewReactionTypeEnum;
use App\Enums\Catalogue\Review\ReviewScopeEnum;
use App\Http\Resources\Catalogue\ReviewMediaResource;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $review_id
 * @property mixed $scope
 * @property mixed $review_rating
 * @property mixed $review_rating_a
 * @property mixed $review_rating_b
 * @property mixed $review_rating_c
 * @property mixed $review_rating_d
 * @property mixed $review_rating_e
 * @property mixed $review_message
 * @property mixed $review_is_public
 * @property mixed $review_status
 * @property mixed $created_at
 * @property mixed $asset_code
 * @property mixed $asset_name
 * @property mixed $family_code
 * @property mixed $family_name
 * @property mixed $order_reference
 * @property mixed $quantity_ordered
 * @property mixed $review_images
 * @property mixed $shop_name
 * @property mixed $replied
 * @property mixed $reply_message
 * @property mixed $reply_at
 * @property mixed $reply_by_name
 * @property mixed $likes
 * @property mixed $dislikes
 * @property mixed $replay_likes
 * @property mixed $replay_dislikes
 */
class RetinaOrderReviewListResource extends JsonResource
{
    public function toArray($request): array
    {
        $reviewImages = $this->review_images
            ? ReviewMediaResource::collection($this->review_images)->toArray(request())
            : [];

        $reviewReaction = $this->review_reaction;
        $replyReaction = $this->reply_reaction;

        $scope = $this->scope instanceof ReviewScopeEnum ? $this->scope->value : (string) $this->scope;
        $name  = match ($scope) {
            'product' => $this->asset_name,
            'family'  => $this->family_name,
            default   => $this->shop_name,
        };
        $code = match ($scope) {
            'product' => $this->asset_code,
            'family'  => $this->family_code,
            default   => null,
        };

        return [
            'review_id'        => (int) $this->review_id,
            'scope'            => $scope,
            'name'             => $name,
            'code'             => $code,
            'quantity_ordered' => $this->quantity_ordered !== null ? (float) $this->quantity_ordered : null,
            'review_status'    => $this->review_status,
            'created_at'       => $this->created_at,
            'review'           => [
                'review_id' => (int) $this->review_id,
                'rating'    => $this->review_rating !== null ? (float) $this->review_rating : null,
                'message'   => $this->review_message,
                'is_public' => (bool) $this->review_is_public,
                'status'    => $this->review_status,
                'review_images'          => $reviewImages,
                'likes'           => (int) ($this->likes ?? 0),
                'dislikes'        => (int) ($this->dislikes ?? 0),
                'replay_likes'    => (int) ($this->replay_likes ?? 0),
                'replay_dislikes' => (int) ($this->replay_dislikes ?? 0),
                'reply' => $this->replied ? [
                    'message'      => $this->reply_message,
                    'at'           => $this->reply_at,
                    'contact_name' => $this->reply_by_name,
                ] : null,
            ],
            'review_reaction'      => ReviewReactionTypeEnum::getValue($reviewReaction),
            'reply_reaction'       => ReviewReactionTypeEnum::getValue($replyReaction),
        ];
    }
}
