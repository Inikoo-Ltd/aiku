<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 24 Mar 2024 20:31:47 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Goods;

use App\Models\Goods\TradeUnit;
use Illuminate\Http\Resources\Json\JsonResource;


class TradeUnitResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var TradeUnit $tradeUnit */
        $tradeUnit = $this;

        return array(
            'slug'                  => $tradeUnit->slug,
            'status'                => $tradeUnit->status,
            'code'                  => $tradeUnit->code,
            'name'                  => $tradeUnit->name,
            'description'           => $tradeUnit->description,
            'barcode'               => $tradeUnit->barcode,
            'gross_weight'          => $tradeUnit->gross_weight,
            'marketing_weight'      => $tradeUnit->marketing_weight,
            'marketing_dimensions'  => $tradeUnit->marketing_dimensions,
            'volume'                => $tradeUnit->volume,
            'type'                  => $tradeUnit->type,
            'image_id'              => $tradeUnit->image_id,
            'name_i8n'              => $tradeUnit->name_i8n,
            'description_i8n'       => $tradeUnit->description_i8n,
            'description_title_i8n' => $tradeUnit->description_title_i8n,
            'description_extra_i8n' => $tradeUnit->description_extra_i8n,

        );
    }
}
