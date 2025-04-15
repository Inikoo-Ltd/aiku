<?php

/*
 * author Arya Permana - Kirin
 * created on 02-04-2025-15h-09m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Platform;

use Illuminate\Http\Resources\Json\JsonResource;

class PlatformsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'      => $this->id,
            'code'    => $this->code,
            'name'    => $this->name,
            'type'    => $this->type
        ];
    }
}
