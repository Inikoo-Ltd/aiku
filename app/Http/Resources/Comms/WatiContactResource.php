<?php

/*
 * Author: andiferdiawan (https://github.com/andiferdiawan)
 * Created: Wednesday, 21 May 2026 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, andiferdiawan
 */

namespace App\Http\Resources\Comms;

use Illuminate\Http\Resources\Json\JsonResource;

class WatiContactResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'wati_id'         => $this->wati_id,
            'wa_id'           => $this->wa_id,
            'phone'           => $this->phone,
            'name'            => $this->name,
            'contact_status'  => $this->contact_status,
            'source'          => $this->source,
            'opted_in'        => $this->opted_in,
            'allow_broadcast' => $this->allow_broadcast,
            'allow_sms'       => $this->allow_sms,
            'segments'        => $this->segments,
            'custom_params'   => $this->custom_params,
            'synced_at'       => $this->synced_at,
            'customer'        => $this->whenLoaded('customer', fn () => [
                'id'   => $this->customer->id,
                'slug' => $this->customer->slug,
                'name' => $this->customer->name,
            ]),
        ];
    }
}
