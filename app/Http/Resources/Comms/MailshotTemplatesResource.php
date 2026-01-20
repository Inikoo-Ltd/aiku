<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 20 Jan 2026 08:43:29 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Http\Resources\Comms;

use Illuminate\Http\Resources\Json\JsonResource;

class MailshotTemplatesResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'state'             => $this->state,
            'subject'           => $this->subject,
            'shop_name'         => $this->name,
            'snapshot_layout'   => $this->snapshot_layout,
            'created_at'        => $this->created_at,
            'sent_at'           => $this->sent_at,
        ];
    }
}
