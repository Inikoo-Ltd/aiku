<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Mar 2024 21:11:53 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $code
 * @property number $quantity_in_locations
 * @property number $number_location
 * @property number $unit_value
 * @property string $slug
 * @property string $description
 * @property string $family_slug
 * @property string $family_code
 * @property string $name
 * @property string $discontinued_in_organisation_at
 * @property mixed $state
 * @property mixed $quantity
 * @property mixed $organisation_name
 * @property mixed $organisation_slug
 * @property mixed $warehouse_slug
 * @property mixed $packed_in
 * @property mixed $quantity_available
 * @property mixed $id
 * @property mixed $organisation_code
 */
class OrgStocksResource extends JsonResource
{
    public function toArray($request): array
    {


        return [
            'id'                              => $this->id,
            'slug'                            => $this->slug,
            'code'                            => $this->code,
            'state'                           => $this->state->stateIcon()[$this->state->value],
            'name'                            => $this->name,
            'quantity'                        => $this->quantity,
            'quantity_available'              => trimDecimalZeros($this->quantity_available),
            'quantity_in_locations'           => trimDecimalZeros($this->quantity_in_locations),
            'unit_value'                      => $this->unit_value,
            'number_locations'                => $this->number_location,
            'quantity_locations'              => $this->quantity_in_locations,
            'family_slug'                     => $this->family_slug,
            'family_code'                     => $this->family_code,
            'discontinued_in_organisation_at' => $this->discontinued_in_organisation_at,
            'organisation_name'               => $this->organisation_name,
            'organisation_code'               => $this->organisation_code,
            'organisation_slug'               => $this->organisation_slug,
            'warehouse_slug'                  => $this->warehouse_slug,
            'packed_in'                       => trimDecimalZeros($this->packed_in),


            'pick_fractional' => ($this->quantity && $this->packed_in) ? riseDivisor(divideWithRemainder(findSmallestFactors($this->quantity)), $this->packed_in) : [],
        ];
    }
}
