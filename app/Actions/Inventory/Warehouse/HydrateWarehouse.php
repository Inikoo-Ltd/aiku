<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 19 Oct 2022 18:37:07 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\Warehouse;

use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydrateFulfilments;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydrateLocations;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePalletDeliveries;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePalletReturns;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePallets;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydrateStocks;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydrateStoredItemAudits;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydrateStoredItems;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydrateWarehouseAreas;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Inventory\Warehouse;

class HydrateWarehouse
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:warehouses {organisations?*} {--s|slugs=}';

    public function __construct()
    {
        $this->model = Warehouse::class;
    }

    public function handle(Warehouse $warehouse): void
    {
        WarehouseHydrateLocations::run($warehouse);
        WarehouseHydrateStocks::run($warehouse);
        WarehouseHydrateWarehouseAreas::run($warehouse);
        WarehouseHydrateFulfilments::run($warehouse);
        WarehouseHydratePallets::run($warehouse);
        WarehouseHydratePalletDeliveries::run($warehouse);
        WarehouseHydratePalletReturns::run($warehouse);
        WarehouseHydrateStoredItemAudits::run($warehouse);
        WarehouseHydrateStoredItems::run($warehouse);

    }


}
