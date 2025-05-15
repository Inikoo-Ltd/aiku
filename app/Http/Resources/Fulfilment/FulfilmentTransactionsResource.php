<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 05 Jul 2024 12:12:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Fulfilment;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $asset_id
 * @property mixed $asset_type*@property mixed $id
 * @property mixed $asset_slug
 * @property mixed $asset_code
 * @property mixed $asset_name
 * @property mixed $asset_price
 * @property mixed $asset_unit
 * @property mixed $asset_units
 * @property mixed $currency_code
 * @property mixed $quantity
 * @property mixed $id
 * @property mixed $is_auto_assign
 * @property mixed $historic_assets_id
 */
class FulfilmentTransactionsResource extends JsonResource
{
    public function toArray($request): array
    {
        if ($this->asset_type == 'service') {
            $unitAbbreviation = 's';
            $unitLabel        = __('service');
        } else {
            $unitAbbreviation = 'u';
            $unitLabel        = __('unit');
        }
        return [
            'id'                => $this->id,
            'asset_slug'        => $this->asset_slug,
            'code'              => $this->code,
            'name'              => $this->name,
            'price'             => $this->price,
            'unit'              => $this->unit,
            'units'             => $this->units,
            'currency_code'     => $this->currency_code,
            'unit_abbreviation' => $unitAbbreviation,
            'unit_label'        => $unitLabel,
            'quantity'          => (int) $this->quantity,
            'total'             => $this->net_amount,
            'edit_type'         => $this->edit_type ?? null,
            'is_auto_assign'    => $this->is_auto_assign,
            'discount'          => (int) $this->discount
            // 'historic_assets_id'=> $this->historic_assets_id



        ];
    }
}
