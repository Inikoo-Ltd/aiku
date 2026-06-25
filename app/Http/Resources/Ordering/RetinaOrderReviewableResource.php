<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 Jun 2026 19:52:53 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Ordering;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $asset_code
 * @property mixed $asset_name
 * @property mixed $family_code
 * @property mixed $family_name
 * @property mixed $review_rating
 * @property mixed $review_id
 * @property mixed $review_status
 * @property mixed $review_rating_a
 * @property mixed $review_rating_b
 * @property mixed $review_rating_c
 * @property mixed $review_rating_d
 * @property mixed $review_rating_e
 * @property mixed $review_message
 * @property mixed $quantity_ordered
 * @property mixed $review_is_public
 */
class RetinaOrderReviewableResource extends JsonResource
{
    public function toArray($request): array
    {
        $orderId      = (int)($this->order_id ?? 0);
        $reviewableId = $this->reviewable_id;
        $scope        = (string)($this->reviewable_type ?? '');

        return [
            'id'               => $reviewableId,
            'asset_code'       => $this->asset_code,
            'asset_name'       => $this->asset_name,
            'family_code'      => $this->family_code,
            'family_name'      => $this->family_name,
            'quantity_ordered' => $this->quantity_ordered !== null ? (float)$this->quantity_ordered : null,
            'review_rating'    => $this->review_rating !== null ? (float)$this->review_rating : null,
            'review'           => [
                'review_id'    => $this->review_id ? (int)$this->review_id : null,
                'status'       => $this->review_status,
                'rating'       => $this->review_rating !== null ? (float)$this->review_rating : null,
                'rating_a'     => $this->review_rating_a !== null ? (int)$this->review_rating_a : null,
                'rating_b'     => $this->review_rating_b !== null ? (int)$this->review_rating_b : null,
                'rating_c'     => $this->review_rating_c !== null ? (int)$this->review_rating_c : null,
                'rating_d'     => $this->review_rating_d !== null ? (int)$this->review_rating_d : null,
                'rating_e'     => $this->review_rating_e !== null ? (int)$this->review_rating_e : null,
                'message'      => $this->review_message,
                'is_public'    => !($this->review_is_public !== null) || $this->review_is_public,
                'scope'        => $scope,
                'reviewable_id' => $reviewableId,
                'order_id'     => $orderId,
            ],
        ];
    }
}
