<?php

/*
 * Author: Louis Perez
 * Created: Thu, 9 Apr 2026 15:28
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStockMovement;

use App\Actions\Inventory\OrgStock\Stock\Concerns\CalculatesOrgStockHistories;
use App\Models\Inventory\OrgStockMovement;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Lorisleiva\Actions\Concerns\AsAction;

class CalculateRunningQuantityOrgStockMovement implements ShouldBeUniqueUntilProcessing
{
    use AsAction;
    use CalculatesOrgStockHistories;

    public string $jobQueue = 'stock-history-urgent';

    public function getJobUniqueId(?int $orgStockMovementId): string
    {
        return $orgStockMovementId ?? 'int';
    }

    public function handle(?int $orgStockMovementId): ?OrgStockMovement
    {
        if (!$orgStockMovementId) {
            return null;
        }

        $orgStockMovement = OrgStockMovement::find($orgStockMovementId);

        if (!$orgStockMovement) {
            return null;
        }
        $orgStock = $orgStockMovement->orgStock;


        $runningQuantityOrg = 0;
        $runningQuantity    = 0;


        foreach ($orgStock->locations as $location) {
            $stockInLocation = $this->getStockQuantity($orgStock, $location, $orgStockMovement->date);
            if ($location->id == $orgStockMovement->location_id) {
                $runningQuantity = $stockInLocation;
            }
            $runningQuantityOrg += $stockInLocation;
        }

        $orgStockMovement->update([
            'running_quantity'           => $runningQuantity,
            'running_quantity_org_stock' => $runningQuantityOrg,
        ]);

        return $orgStockMovement;
    }
}
