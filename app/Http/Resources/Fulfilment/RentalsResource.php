<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 18 Apr 2024 09:27:56 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Fulfilment;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $code
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $rental_price
 * @property string $description
 * @property mixed $type
 * @property mixed $auto_assign_asset
 * @property mixed $auto_assign_asset_type
 * @property mixed $currency_code
 * @property mixed $unit
 * @property mixed $state
 */
class RentalsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'code' => $this->code,
            'name' => $this->name,
            'sales' => $this->sales,
            'rental_price' => $this->rental_price,
            'currency_code' => $this->currency_code,
            'unit' => $this->unit,
            'unit_abbreviation' => $this->unit->abbreviations()[$this->unit->value],
            'unit_label' => $this->unit->labels()[$this->unit->value],
            'description' => $this->description,
            'auto_assign_asset_type' => $this->auto_assign_asset_type,
            'auto_assign_asset' => $this->auto_assign_asset,
            'state_label' => $this->state->labels()[$this->state->value],
            'state_icon' => $this->state->stateIcon()[$this->state->value],
        ];
    }
}
