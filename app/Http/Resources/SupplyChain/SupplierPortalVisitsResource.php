<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\SupplyChain;

use Illuminate\Http\Resources\Json\JsonResource;

class SupplierPortalVisitsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'created_at'   => $this->created_at,
            'ip'           => $this->ip,
            'user_agent'   => $this->user_agent,
            'country_code' => $this->country_code,
            'city'         => $this->city,
            'reference'    => $this->token_prefix ? $this->token_prefix.'…' : '#'.$this->link_id,
        ];
    }
}
