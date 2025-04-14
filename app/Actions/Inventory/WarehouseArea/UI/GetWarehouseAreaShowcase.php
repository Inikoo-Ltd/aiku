<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Thu, 25 May 2023 15:03:06 Central European Summer Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Inventory\WarehouseArea\UI;

use App\Models\Inventory\WarehouseArea;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWarehouseAreaShowcase
{
    use AsObject;

    public function handle(WarehouseArea $warehouseArea): array
    {
        return [
            'created_at'            => $warehouseArea->created_at,
            'id'            => $warehouseArea->id,
            'name'          => $warehouseArea->name,
            'warehouse'     => $warehouseArea->warehouse,
            'code'          => $warehouseArea->code,
            'unit_quantity'          => (int) $warehouseArea->unit_quantity,
            'stats'          => $warehouseArea->stats,
            'xxx'           => $warehouseArea,
        ];
    }
}
