<?php

/*
 * Author: andiferdiawan (https://github.com/andiferdiawan)
 * Created: Wednesday, 21 May 2026 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, andiferdiawan
 */

namespace App\Http\Resources\Comms;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomerForWatiResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'slug'       => $this->slug,
            'name'       => $this->name,
            'phone'      => $this->phone,
            'email'      => $this->email,
            'created_at' => $this->created_at,
        ];
    }
}
