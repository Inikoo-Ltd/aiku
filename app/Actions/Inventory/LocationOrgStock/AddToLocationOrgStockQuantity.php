<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Sept 2026 16:40:00 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\LocationOrgStock;

use App\Actions\Inventory\Location\Hydrators\LocationHydrateOrgStocks;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateQuantityInLocations;
use App\Actions\Inventory\OrgStock\SetOrgStockPickingLocation;
use App\Actions\Inventory\OrgStock\Stock\CalculateOrgStockCurrentStockHistories;
use App\Models\Inventory\LocationOrgStock;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The only way a stock movement changes what a location holds: one atomic statement on the
 * write connection, so concurrent movements on the same location queue on the row lock and
 * none of them is lost (HELP-3418). Never recompute the level from the movement ledger here,
 * the ledger is read on another connection and cannot see the caller's open transaction.
 */
class AddToLocationOrgStockQuantity
{
    use AsAction;

    public function handle(LocationOrgStock $locationOrgStock, float $quantity): float
    {
        $newQuantity = (float)DB::selectOne(
            'update location_org_stocks set quantity = quantity + ?, updated_at = now() where id = ? returning quantity',
            [$quantity, $locationOrgStock->id],
            false
        )->quantity;

        $locationOrgStock->setAttribute('quantity', $newQuantity);
        $locationOrgStock->syncOriginalAttribute('quantity');

        if ($quantity != 0.0) {
            OrgStockHydrateQuantityInLocations::run($locationOrgStock->org_stock_id);
            CalculateOrgStockCurrentStockHistories::dispatch($locationOrgStock->org_stock_id);
            LocationHydrateOrgStocks::dispatch($locationOrgStock->location);
            SetOrgStockPickingLocation::dispatch($locationOrgStock->org_stock_id)->delay(2);
        }

        return $newQuantity;
    }
}
