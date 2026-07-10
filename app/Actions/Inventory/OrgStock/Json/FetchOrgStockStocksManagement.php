<?php

/*
 * Author: Vika Aqordi <aqordivika@yahoo.co.id>
 * Created: Fri, 10 Jul 2026 14:00:00 Bali, Indonesia
 * Copyright (c) 2026, Vika Aqordi
*/

namespace App\Actions\Inventory\OrgStock\Json;

use App\Actions\Inventory\OrgStock\UI\GetOrgStockShowcase;
use App\Actions\OrgAction;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;

/**
 * Returns the stocks management showcase payload for an org stock in a warehouse,
 * so stock can be moved/managed across locations from the waiting items screen.
 */
class FetchOrgStockStocksManagement extends OrgAction
{
    public function handle(Warehouse $warehouse, OrgStock $orgStock): Collection
    {
        return GetOrgStockShowcase::run($warehouse, $orgStock); // Should takes only stocks management (not enough time)
    }

    public function jsonResponse(Collection $showcase): array
    {
        return $showcase->toArray();
    }

    public function asController(Warehouse $warehouse, OrgStock $orgStock, ActionRequest $request): Collection
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse, $orgStock);
    }
}
