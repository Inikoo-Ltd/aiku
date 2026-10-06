<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 06 Oct 2026 12:00:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\LocationOrgStock;

use App\Models\Inventory\LocationOrgStock;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Brings a location to what its last audit plus movements say it holds, for writers that can
 * back-date a movement (Aurora fetches). The ledger is read under the location's row lock and on
 * the same connection, and only the difference is applied, so a movement written meanwhile by
 * someone else is never overwritten (HELP-3622).
 */
class SyncLocationOrgStockQuantityFromMovements
{
    use AsAction;

    public function handle(LocationOrgStock $locationOrgStock): float
    {
        return DB::transaction(function () use ($locationOrgStock) {
            $lockedQuantity = (float)LocationOrgStock::whereKey($locationOrgStock->id)->lockForUpdate()->value('quantity');
            $expected       = GetLocationOrgStockQuantity::run($locationOrgStock->orgStock, $locationOrgStock->location, inCurrentTransaction: true);

            return AddToLocationOrgStockQuantity::run($locationOrgStock, $expected - $lockedQuantity);
        });
    }
}
