<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 14 Jun 2025 11:34:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\InventoryDailySnapshot;

use App\Actions\OrgAction;
use App\Models\Inventory\InventoryDailySnapshot;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;

class StoreInventoryDailySnapshot extends OrgAction
{
    public function handle(Warehouse $warehouse, OrgStock $orgStock, array $modelData): InventoryDailySnapshot
    {
        data_set($modelData, 'warehouse_id', $warehouse->id);

        return $orgStock->inventoryDailySnapshots()->create($modelData);
    }

    public function action(Warehouse $warehouse, OrgStock $orgStock, array $modelData, int $hydratorsDelay = 0): InventoryDailySnapshot
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;


        $this->initialisationFromWarehouse($warehouse, $modelData);

        return $this->handle($warehouse, $orgStock, $this->validatedData);
    }

}
