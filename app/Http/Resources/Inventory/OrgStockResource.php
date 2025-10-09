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
        // /** @var OrgStock $orgStock */
        $orgStock = $this;

        $locationStock = ($orgStock->locationOrgStocks ?? collect())->first();

        // Akses properti secara langsung karena mereka ada di objek hasil join
        return [
            'id'                  => $this->id,
            'code'                => $this->code,
            'name'                => $this->name,
            'slug'                => $this->slug,
            'unit_value'          => $this->unit_value,
            'type'                => $this->type,
            'picking_priority'    => $this->picking_priority,
            'value'               => $this->value,
            'dropshipping_pipe'   => $this->dropshipping_pipe,
            'quantity'            => (int) $this->quantity,
            'notes'               => $this->notes,
        ];
    }
}
