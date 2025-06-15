<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 May 2023 22:49:26 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\WarehouseArea\Hydrators;

use App\Models\Inventory\WarehouseArea;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class WarehouseAreaHydrateStocks implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(WarehouseArea $warehouseArea): string
    {
        return $warehouseArea->id;
    }

    public function handle(WarehouseArea $warehouseArea): void
    {

        $warehouseArea->stats()->update(
            [
                'stock_value'            => $warehouseArea->locations()->sum('stock_value'),
                'stock_commercial_value' => $warehouseArea->locations()->sum('stock_commercial_value'),
            ]
        );
    }


}
