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

    private OrgStockMovement $orgStockMovement;

    public string $jobQueue = 'stock-history-urgent';

    public function getJobUniqueId(?int $orgStockMovementId): string
    {
        return (string)($orgStockMovementId ?? 'empty');
    }

    public function handle(?int $orgStockMovementId): void
    {
        if (!$orgStockMovementId) {
            return;
        }
        $orgStockMovement = OrgStockMovement::find($orgStockMovementId);
        if (!$orgStockMovement) {
            return;
        }
        $orgStock = $orgStockMovement->orgStock;
        // If you want to loop
        $runningQuantity    = $this->getStockQuantity($orgStock, $orgStockMovement->location, $orgStockMovement->date);
        $runningQuantityOrg = 0;

        foreach ($orgStock->locations as $location) {
            $runningQuantityOrg += $this->getStockQuantity($orgStock, $location, $orgStockMovement->date);
        }

        $orgStockMovement->update([
            'running_quantity'           => $runningQuantity,
            'running_quantity_org_stock' => $runningQuantityOrg,
        ]);
    }
}
