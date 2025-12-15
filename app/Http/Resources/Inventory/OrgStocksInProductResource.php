<?php

/*
 * author Arya Permana - Kirin
 * created on 26-06-2025-15h-33m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $slug
 * @property mixed $code
 * @property mixed $name
 * @property mixed $pivot_notes
 * @property mixed $unit_value
 * @property mixed $discontinued_in_organisation_at
 * @property mixed $family_slug
 * @property mixed $family_code
 * @property mixed $pivot_quantity
 */
class OrgStocksInProductResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'org_stock_id'                    => $this->id,
            'slug'                            => $this->slug,
            'code'                            => $this->code,
            'name'                            => $this->name,
            'notes'                           => $this->pivot_notes,
            'unit_value'                      => $this->unit_value,
            'discontinued_in_organisation_at' => $this->discontinued_in_organisation_at,
            'family_slug'                     => $this->family_slug,
            'family_code'                     => $this->family_code,
            'quantity'                        => $this->pivot_quantity,
        ];
    }
}
