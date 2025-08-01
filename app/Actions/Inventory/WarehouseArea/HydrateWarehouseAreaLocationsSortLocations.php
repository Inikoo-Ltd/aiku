<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 31 Jul 2025 15:24:51 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\WarehouseArea;

use App\Actions\Inventory\Location\Hydrators\LocationHydrateSortCode;
use App\Models\Inventory\WarehouseArea;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class HydrateWarehouseAreaLocationsSortLocations implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(WarehouseArea $warehouseArea): string
    {
        return $warehouseArea->id;
    }

    public function handle(WarehouseArea $warehouseArea): void
    {
        foreach ($warehouseArea->locations as $location) {
            LocationHydrateSortCode::run($location);
        }
    }

}
