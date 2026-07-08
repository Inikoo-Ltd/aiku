<?php

namespace App\Actions\Inventory\OrgStockMovement\Traits;

use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateMovements;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateProductsAvailableQuantity;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateSkuValue;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateStockValue;
use App\Actions\Inventory\OrgStockMovement\CalculateRunningQuantityOrgStockMovement;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Inventory\OrgStockMovement;

trait WithOrgStockMovementHydrator
{
    public function hydrateOrgStockMovement(OrgStockMovement $orgStockMovement)
    {
        $orgStock = $orgStockMovement->orgStock;

        if ($orgStockMovement->type == OrgStockMovementTypeEnum::PURCHASE) {
            OrgStockHydrateStockValue::dispatch($orgStock);//todo do we need to delete this??? maybe yes
            OrgStockHydrateSkuValue::dispatch($orgStock);
        }

        OrgStockHydrateMovements::dispatch($orgStock)->delay(now()->addMinutes(15));
        OrgStockHydrateProductsAvailableQuantity::dispatch($orgStock)->delay(now()->addMinutes(15));
        CalculateRunningQuantityOrgStockMovement::dispatch($orgStockMovement->id)->delay(now()->addMinutes(15));
    }
}
