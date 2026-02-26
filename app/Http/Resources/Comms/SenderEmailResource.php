<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Wednesday, 26 Feb 2026 13:28:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Http\Resources\Comms;

use Illuminate\Http\Resources\Json\JsonResource;

class SenderEmailResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                           => $this->id,
            'email_address'                => $this->email_address,
            'usage_count'                  => $this->usage_count,
            'state'                        => $this->state,
            'last_verification_submitted_at' => $this->last_verification_submitted_at,
            'verified_at'                  => $this->verified_at,
            'created_at'                   => $this->created_at,
            'updated_at'                   => $this->updated_at,
        ];
    }
}
