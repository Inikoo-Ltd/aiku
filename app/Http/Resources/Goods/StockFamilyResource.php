<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 25 Oct 2022 08:17:00 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Goods;

use App\Models\Goods\StockFamily;
use Illuminate\Http\Resources\Json\JsonResource;

class StockFamilyResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var StockFamily $stockFamily */
        $stockFamily = $this;

        return [
            'slug' => $stockFamily->slug,
            'code' => $stockFamily->code,
            'state' => $stockFamily->state,
            'name' => $stockFamily->name,
            'number_stocks' => $stockFamily->stats->number_stocks,
            'created_at' => $stockFamily->created_at,
            'updated_at' => $stockFamily->updated_at,
        ];
    }
}
