<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 28 Aug 2026 20:00:00 British Summer Time, Sheffield, UK
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dispatching;

use Illuminate\Http\Resources\Json\JsonResource;

class DispatchPersonnelPerformanceResource extends JsonResource
{
    public function toArray($request): array
    {
        $kg = (float) $this->weight;

        return [
            'user_id'        => $this->user_id,
            'name'           => $this->name,
            'dns'            => (int) $this->dns,
            'items'          => (int) $this->items,
            'skos'           => (int) $this->skos,
            'weight'         => $kg >= 1000 ? round($kg / 1000, 1) . ' t' : round($kg) . ' kg',
            'parcels'        => (int) ($this->parcels ?? 0),
            'avg_minutes'    => (float) $this->avg_minutes,
            'items_per_hour' => (float) $this->items_per_hour,
        ];
    }
}
