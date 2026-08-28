<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 28 Aug 2026 20:00:00 British Summer Time, Sheffield, UK
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dispatching;

use Illuminate\Http\Resources\Json\JsonResource;

class DispatchPersonnelCurrentWorkResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'user_id'  => $this->user_id,
            'name'     => $this->name,
            'orders'   => json_decode($this->orders ?? '[]', true) ?? [],
            'trolleys' => json_decode($this->trolleys ?? '[]', true) ?? [],
        ];
    }
}
