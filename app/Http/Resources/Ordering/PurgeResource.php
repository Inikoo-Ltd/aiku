<?php

/*
 * author Arya Permana - Kirin
 * created on 04-11-2024-15h-27m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Http\Resources\Ordering;

use Illuminate\Http\Resources\Json\JsonResource;

class PurgeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'state' => $this->state,
            'type' => $this->type,
            'scheduled_at' => $this->scheduled_at,
            'start_at' => $this->start_at,
            'end_at' => $this->end_at,
            'cancelled_at' => $this->cancelled_at,
            'inactive_days' => $this->inactive_days,
        ];
    }
}
