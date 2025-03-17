<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 19 Oct 2022 18:37:19 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\WarehouseArea;

use App\Actions\Inventory\WarehouseArea\Hydrators\WarehouseAreaHydrateLocations;
use App\Actions\Inventory\WarehouseArea\Hydrators\WarehouseAreaHydrateStocks;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Inventory\WarehouseArea;

class HydrateWarehouseArea
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:warehouse_areas {organisations?*} {--i|slugs=}';

    public function __construct()
    {
        $this->model = WarehouseArea::class;
    }

    public function handle(WarehouseArea $warehouseArea): void
    {
        WarehouseAreaHydrateLocations::run($warehouseArea);
        WarehouseAreaHydrateStocks::run($warehouseArea);
    }

}
