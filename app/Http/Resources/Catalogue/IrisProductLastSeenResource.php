<?php

namespace App\Http\Resources\Catalogue;

class IrisProductLastSeenResource extends IrisLuigiBoxRecommendationResource
{
    public function toArray($request): array
    {
        return array_merge(
            parent::toArray($request),
            [
                'webpage_id'   => $this->webpage_id,
                'last_seen_at' => $this->last_seen_at,
            ]
        );
    }
}
