<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $slug
 * @property mixed $code
 * @property mixed $name
 * @property mixed $system_weight
 * @property mixed $measured_weight
 * @property mixed $difference
 * @property mixed $measured_weight_parcels
 */
class OrgStocksWithWrongWeightResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                      => $this->id,
            'slug'                    => $this->slug,
            'code'                    => $this->code,
            'name'                    => $this->name,
            'system_weight'           => (int) $this->system_weight,
            'measured_weight'         => (int) $this->measured_weight,
            'difference'              => (int) $this->difference,
            'measured_weight_parcels' => (int) $this->measured_weight_parcels,
        ];
    }
}
