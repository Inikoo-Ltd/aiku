<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 24 Mar 2024 20:31:47 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Goods;

use Illuminate\Http\Resources\Json\JsonResource;

class TradeUnitFamiliesResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'slug'                    => $this->slug,
            'code'                    => $this->code,
            'name'                    => $this->name,
            'id'                      => $this->id,
            'number_trade_units'      => $this->number_trade_units
        ];
    }
}
