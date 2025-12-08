<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 May 2024 09:45:43 British Summer Time, Sheffield, UK
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
 * @property mixed $auto_assign_trigger
 * @property mixed $auto_assign_subject
 * @property mixed $auto_assign_subject_type
 * @property bool $auto_assign_status
 * @property mixed $is_auto_assign
 * @property mixed $historic_asset_id
 */
class ServicesResource extends JsonResource
{
    public function toArray($request): array
    {
        $autoLabel = '';
        if ($this->is_auto_assign) {
            $trigger = match ($this->auto_assign_trigger) {
                'PalletDelivery' => __('Delivery'),
                'PalletReturn' => __('Return'),
                default => $this->auto_assign_trigger
            };
            $autoLabel = $trigger;
            if ($this->auto_assign_subject == 'Pallet') {
                $autoLabel .= ' : '.match ($this->auto_assign_subject_type) {
                    'pallet' => __('Pallet'),
                    'box' => __('Box'),
                    'oversize' => __('Oversize'),
                    default => $this->auto_assign_trigger
                };
            }
        }

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'code' => $this->code,
            'name' => $this->name,
            'price' => $this->price * ($this->quantity ?? 1),
            'currency_code' => $this->currency_code,
            'unit' => $this->unit,
            'is_pallet_handling' => $this->is_pallet_handling,
            // 'unit_abbreviation'      => $this->unit ? $this->unit->abbreviations()[$this->unit->value] : 's',
            // 'unit_label'             => $this->unit ? $this->unit->labels()[$this->unit->value] : __('service'),
            'unit_abbreviation' => 's',
            'unit_label' => __('service'),
            'quantity' => $this->quantity,
            'total' => $this->quantity * $this->price,
            'state_label' => $this->state->labels()[$this->state->value],
            'state_icon' => $this->state->stateIcon()[$this->state->value],

            'is_auto_assign' => $this->is_auto_assign,
            'auto_assign_trigger' => $this->auto_assign_trigger,
            'auto_assign_subject' => $this->auto_assign_subject,
            'auto_assign_subject_type' => $this->auto_assign_subject_type,
            'auto_assign_status' => $this->auto_assign_status,
            'auto_label' => $autoLabel,
            'historic_asset_id' => $this->historic_asset_id,
            'organisation_name' => $this->organisation_name,
            'organisation_slug' => $this->organisation_slug,
            'shop_name' => $this->shop_name,
            'shop_slug' => $this->shop_slug,
        ];
    }
}
