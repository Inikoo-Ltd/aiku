<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Mar 2024 21:11:53 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Inventory;

use App\Models\Inventory\OrgStock;
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
 */
class OrgStocksResource extends JsonResource
{


    public function toArray($request): array
    {
        /** @var OrgStock $orgStock */
        $orgStock = $this;

        return [
            'id'                              => $orgStock->id,
            'slug'                            => $orgStock->slug,
            'code'                            => $this->code,
            'state'                           => $this->state->stateIcon()[$this->state->value],
            'name'                            => $this->name,
            'quantity'                        => $this->quantity,
            'unit_value'                      => $this->unit_value,
            'number_locations'                => $this->number_location,
            'quantity_locations'              => $this->quantity_in_locations,
            'family_slug'                     => $this->family_slug,
            'family_code'                     => $this->family_code,
            'discontinued_in_organisation_at' => $this->discontinued_in_organisation_at,
            'organisation_name'               => $this->organisation_name,
            'organisation_slug'               => $this->organisation_slug,
            'warehouse_slug'                  => $this->warehouse_slug,
        ];
    }
}
