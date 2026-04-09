<?php

/*
 * author Louis Perez
 * created on 09-04-2026-15h-28m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Inventory\OrgStockMovement;

use App\Actions\Inventory\OrgStock\Stock\Concerns\CalculatesOrgStockHistories;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\OrgStockMovement;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class CalculateRunningQuantityOrgStockMovement implements ShouldBeUniqueUntilProcessing
{
    use AsAction;
    use CalculatesOrgStockHistories;

    private OrgStockMovement $orgStockMovement;

    public string $jobQueue = 'stock-history';

    public function getJobUniqueId(OrgStockMovement $orgStockMovement): string
    {
        return $orgStockMovement->id;
    }

    public function handle(OrgStockMovement $orgStockMovement): void
    {
        $orgStock = $orgStockMovement->orgStock;
        // If you want to loop
        $runningQuantity = $this->getStockQuantity($orgStock, $orgStockMovement->location, $orgStockMovement->date);
        $runningQuantityOrg = 0;

        foreach($orgStock->locations as $location) {
            $runningQuantityOrg += $this->getStockQuantity($orgStock, $location, $orgStockMovement->date);
        }

        $orgStockMovement->update([
            'running_quantity'              => $runningQuantity,
            'running_quantity_org_stock'    => $runningQuantityOrg,
        ]);
    }
}
