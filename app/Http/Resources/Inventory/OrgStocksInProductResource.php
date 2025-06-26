<?php
/*
 * author Arya Permana - Kirin
 * created on 26-06-2025-15h-33m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Inventory;

use App\Models\Inventory\OrgStock;
use Illuminate\Http\Resources\Json\JsonResource;

class OrgStocksInProductResource extends JsonResource
{
    public function toArray($request): array
    {

        return [
            'id'                              => $this->id,
            'slug'                            => $this->slug,
            'code'                            => $this->code,
            'name'                            => $this->name,
            'unit_value'                      => $this->unit_value,
            'number_locations'                => $this->number_location,
            'quantity_locations'              => $this->quantity_in_locations,
            'discontinued_in_organisation_at' => $this->discontinued_in_organisation_at,
            'family_slug'                     => $this->family_slug,
            'family_code'                     => $this->family_code,
            'pivot_id'                        => $this->pivot_id,
            'pivot_quantity'                  => $this->pivot_quantity,
        ];
    }
}
