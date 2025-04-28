<?php

/*
 * author Arya Permana - Kirin
 * created on 28-04-2025-15h-35m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\CRM;

use Illuminate\Http\Resources\Json\JsonResource;

class PollOptionsResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var PollOption $pollOption */
        $pollOption = $this;
        return [
            'id'                 => $pollOption->id,
            'slug'               => $pollOption->slug,
            'value'              => $pollOption->value,
            'label'              => $pollOption->label
        ];
    }
}
