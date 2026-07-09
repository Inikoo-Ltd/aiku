<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 09 Jul 2026 11:07:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Inventory;

use App\Models\Inventory\WarehouseArea;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class WarehouseAreasSearchResultResource extends JsonResource
{
    public function toArray($request): array|Arrayable|JsonSerializable
    {
        /** @var WarehouseArea $warehouseArea */
        $warehouseArea = $this;

        return [
            'code' => $warehouseArea->code,
            'name' => $warehouseArea->name,
            'id'   => $warehouseArea->id,
        ];
    }
}
