<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Aug 2024 11:12:11 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $pivot
 * @property int $id
 * @property mixed $quantity
 * @property mixed $value
 * @property mixed $audited_at
 * @property mixed $commercial_value
 * @property mixed $type
 * @property mixed $picking_priority
 * @property mixed $notes
 * @property mixed $data
 * @property mixed $settings
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property mixed $location
 */
class LocationOrgStocksResource extends JsonResource
{
    public static $wrap = null;

    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'code'             => $this->location->code,
            'quantity'         => $this->quantity,
            'value'            => $this->value,
            'audited_at'       => $this->audited_at,
            'commercial_value' => $this->commercial_value,
            'type'             => $this->type,
            'picking_priority' => $this->picking_priority,
            'notes'            => $this->notes,
            'data'             => $this->data,
            'settings'         => $this->settings,
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
            'location'         => new LocationsResource($this->location)
        ];
    }
}
