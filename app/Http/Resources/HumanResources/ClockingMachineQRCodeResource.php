<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Jul 2026 09:10:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\HumanResources;

use Illuminate\Http\Resources\Json\JsonResource;

class ClockingMachineQRCodeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                     => $this->id,
            'label'                  => $this->label,
            'hash'                   => $this->hash,
            'qr_value'               => implode(':', array_filter([$this->label, $this->hash])),
            'active'                 => (bool) $this->active,
            'number_clockings'       => (int) $this->number_clockings,
            'number_different_staff' => (int) $this->number_different_staff,
            'last_used_at'           => $this->last_used_at,
            'created_at'             => $this->created_at,
        ];
    }
}
