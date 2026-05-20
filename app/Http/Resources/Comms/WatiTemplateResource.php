<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Wednesday, 20 May 2026 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Http\Resources\Comms;

use Illuminate\Http\Resources\Json\JsonResource;

class WatiTemplateResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'element_name' => $this->element_name,
            'category'     => $this->category,
            'status'       => $this->status,
            'created_at'   => $this->created_at,
        ];
    }
}
