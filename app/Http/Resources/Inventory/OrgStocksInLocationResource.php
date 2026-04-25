<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Sat, 22 Oct 2022 18:52:52 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Inventory;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $code
 * @property mixed $name
 * @property mixed $slug
 * @property mixed $sku_value
 * @property mixed $type
 * @property mixed $picking_priority
 * @property mixed $value
 * @property mixed $dropshipping_pipe
 * @property mixed $quantity
 * @property mixed $notes
 */
class OrgStocksInLocationResource extends JsonResource
{
    use HasSelfCall;

    public static $wrap = null;

    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'code'              => $this->code,
            'name'              => $this->name,
            'slug'              => $this->slug,
            'sku_value'         => $this->sku_value,
            'type'              => $this->type,
            'picking_priority'  => $this->picking_priority,
            'value'             => $this->value,
            'dropshipping_pipe' => $this->dropshipping_pipe,
            'quantity'          => $this->quantity,
            'notes'             => $this->notes,
        ];
    }
}
