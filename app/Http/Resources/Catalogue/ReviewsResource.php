<?php

namespace App\Http\Resources\Catalogue;

use Illuminate\Http\Resources\Json\JsonResource;

class ReviewsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                   => $this->id,
            'customer_name'        => $this->customer_name ?? $this->customer?->name,
            'status'               => $this->status?->value ?? $this->status,
            'rating'               => (int) $this->rating,
            'title'                => $this->title,
            'message'              => $this->message,
            'is_verified_purchase' => (bool) $this->is_verified_purchase,
            'helpful_count'        => (int) $this->helpful_count,
            'created_at'           => $this->created_at,
        ];
    }
}
