<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 03 Aug 2025 14:23:43 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStockMovement;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\OrgStockMovement;

class DeleteOrgStockMovement extends OrgAction
{
    use WithActionUpdate;


    public function handle(OrgStockMovement $orgStockMovement): OrgStockMovement
    {

        $orgStockMovement->delete();
        $locationOrgStock = LocationOrgStock::where('location_id', $orgStockMovement->location->id)->where('org_stock_id', $orgStockMovement->orgStock->id)->first();

        if ($locationOrgStock) {
            // todo do a run stock
        }

        return $orgStockMovement;

    }





    public function action(OrgStockMovement $orgStockMovement): OrgStockMovement
    {

        $this->asAction       = true;
        $this->initialisation($orgStockMovement->organisation, []);

        return $this->handle($orgStockMovement);
    }


}
