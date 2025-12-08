<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 May 2024 10:39:45 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Fulfilment;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $code
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $price
 * @property string $description
 * @property mixed $type
 * @property mixed $auto_assign_asset
 * @property mixed $auto_assign_asset_type
 * @property mixed $currency_code
 * @property mixed $unit
 * @property mixed $state
 * @property int $quantity
 * @property int $pallet_delivery_id
 * @property mixed $historic_asset_id
 */
class PhysicalGoodsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'slug' => $this->slug,
            'price' => $this->price,
            'currency_code' => $this->currency_code,
            'unit' => $this->unit,
            // 'unit_abbreviation'      => $this->unit ? $this->unit->abbreviations()[$this->unit->value] : 's',
            // 'unit_label'             => $this->unit ? $this->unit->labels()[$this->unit->value] : __('service'),
            'unit_abbreviation' => 's',
            'unit_label' => __('service'),
            'description' => $this->description,
            'auto_assign_asset_type' => $this->auto_assign_asset_type,
            'auto_assign_asset' => $this->auto_assign_asset,
            'state_label' => $this->state->labels()[$this->state->value],
            'state_icon' => $this->state->stateIcon()[$this->state->value],
            'quantity' => $this->quantity,
            'total' => $this->quantity * $this->price,
            'historic_asset_id' => $this->historic_asset_id,

        ];
    }
}
