<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Sat, 22 Oct 2022 18:52:52 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Inventory;

use App\Http\Resources\HasSelfCall;
use App\Models\Inventory\OrgStock;
use Illuminate\Http\Resources\Json\JsonResource;

class OrgStockResource extends JsonResource
{
    use HasSelfCall;
    public static $wrap = null;
    public function toArray($request): array
    {
        /** @var OrgStock $orgStock */
        $orgStock = $this;

        return [
            'id'                 => $orgStock->id,
            'slug'               => $orgStock->slug,
            'code'               => $orgStock->code,
            'unit_value'         => $orgStock->unit_value,
            'description'        => $orgStock->stock?->description,
            'number_locations'   => $orgStock->stats?->number_locations,
            'quantity_locations' => $orgStock->quantity_in_locations,
            'photo'              => $orgStock->stock?->imageSources()
        ];
    }
}
