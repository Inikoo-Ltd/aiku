<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 03 Aug 2025 14:23:43 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStockMovement;

use App\Actions\Inventory\LocationOrgStock\AddToLocationOrgStockQuantity;
use App\Actions\Inventory\OrgStockMovement\Traits\WithOrgStockMovementHydrator;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Events\BroadcastStockMovement;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\OrgStockMovementBatch;
use App\Models\Inventory\OrgStockMovement;
use Illuminate\Support\Facades\DB;

class DeleteOrgStockMovement extends OrgAction
{
    use WithActionUpdate;
    use WithOrgStockMovementHydrator;

    public function handle(OrgStockMovement $orgStockMovement): OrgStockMovement
    {
        $locationOrgStock = LocationOrgStock::where('location_id', $orgStockMovement->location_id)
            ->where('org_stock_id', $orgStockMovement->org_stock_id)
            ->first();

        if ($locationOrgStock !== null) {
            $runningQuantity = DB::transaction(function () use ($locationOrgStock, $orgStockMovement) {
                LocationOrgStock::whereKey($locationOrgStock->id)->lockForUpdate()->value('id');
                OrgStockMovementBatch::where('org_stock_movement_id', $orgStockMovement->id)->delete();
                $quantity = OrgStockMovement::whereKey($orgStockMovement->id)->value('quantity');

                return AddToLocationOrgStockQuantity::run($locationOrgStock, -(float)$quantity);
            });

            $runningQuantityOrg = DB::table('location_org_stocks')
                ->where('org_stock_id', $orgStockMovement->org_stock_id)->sum('quantity');

            $orgStockMovement->update([
                'running_quantity' => $runningQuantity,
                'running_quantity_org_stock' => $runningQuantityOrg,
            ]);


            BroadcastStockMovement::dispatch($locationOrgStock);
        }

        $orgStockMovement->delete();

        $this->hydrateOrgStockMovement($orgStockMovement);


        return $orgStockMovement;
    }

    public function action(OrgStockMovement $orgStockMovement): OrgStockMovement
    {
        $this->asAction = true;
        $this->initialisation($orgStockMovement->organisation, []);

        return $this->handle($orgStockMovement);
    }
}
