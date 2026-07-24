<?php

namespace App\Http\Resources\Catalogue;

class IrisProductAlternativeResource extends IrisLuigiBoxRecommendationResource
{
    public function toArray($request): array
    {
        return array_merge(
            parent::toArray($request),
            [
                'webpage_id'        => $this->webpage_id,
                'alternative_score' => round((float) $this->alternative_score, 4),
            ]
        );
    }
}
