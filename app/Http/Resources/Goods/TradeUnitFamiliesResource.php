<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 24 Mar 2024 20:31:47 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Goods;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $slug
 * @property mixed $code
 * @property mixed $name
 * @property mixed $id
 * @property mixed $number_trade_units
 * @property mixed $number_trade_units_status_in_process
 * @property mixed $number_trade_units_status_active
 * @property mixed $number_trade_units_status_discontinued
 * @property mixed $number_trade_units_status_anomality
 */
class TradeUnitFamiliesResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'slug'                                   => $this->slug,
            'code'                                   => $this->code,
            'name'                                   => $this->name,
            'id'                                     => $this->id,
            'number_trade_units'                     => $this->number_trade_units,
            'number_trade_units_status_in_process'   => $this->number_trade_units_status_in_process,
            'number_trade_units_status_active'       => $this->number_trade_units_status_active,
            'number_trade_units_status_discontinued' => $this->number_trade_units_status_discontinued,
            'number_trade_units_status_anomality'    => $this->number_trade_units_status_anomality,
        ];
    }
}
