<?php

namespace App\Actions\Inventory\OrgStockMovement\Traits;

use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateMovements;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateProductsAvailableQuantity;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateSkuValue;
use App\Actions\Inventory\OrgStockMovement\CalculateOrgStockMovementRunningValues;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Inventory\OrgStockMovement;

trait WithOrgStockMovementHydrator
{
    public function hydrateOrgStockMovement(OrgStockMovement $orgStockMovement): void
    {
        $orgStock = $orgStockMovement->orgStock;

        if ($orgStockMovement->type == OrgStockMovementTypeEnum::PURCHASE) {
            OrgStockHydrateSkuValue::dispatch($orgStock);
        }

        OrgStockHydrateMovements::dispatch($orgStock)->delay(now()->addMinutes());
        CalculateOrgStockMovementRunningValues::dispatch($orgStock->id)->delay(now()->addMinute());
        OrgStockHydrateProductsAvailableQuantity::dispatch($orgStock)->delay(now()->addMinutes());
    }
}
