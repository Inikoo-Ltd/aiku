<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 29 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\SupplyChain;

use Illuminate\Http\Resources\Json\JsonResource;

class AssignableSuppliersResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'slug'       => $this->slug,
            'code'       => $this->code,
            'name'       => $this->name,
            'location'   => $this->location,
            'agent_slug' => $this->agent_slug,
            'agent_code' => $this->agent_code,
            'agent_name' => $this->agent_name,

            'organisations_losing_supplier' => $this->organisations_losing_supplier,
        ];
    }
}
