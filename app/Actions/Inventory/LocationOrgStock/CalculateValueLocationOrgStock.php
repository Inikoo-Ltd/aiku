<?php

/*
 * author Louis Perez
 * created on 09-04-2026-12h-53m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Inventory\LocationOrgStock;

use App\Actions\Inventory\OrgStock\Stock\Concerns\CalculatesOrgStockHistories;
use App\Models\Inventory\LocationOrgStock;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Lorisleiva\Actions\Concerns\AsAction;

class CalculateValueLocationOrgStock implements ShouldBeUniqueUntilProcessing
{
    use AsAction;
    use CalculatesOrgStockHistories;

    private LocationOrgStock $locationOrgStock;

    public string $jobQueue = 'urgent';

    public function getJobUniqueId(LocationOrgStock $locationOrgStock): string
    {
        return $locationOrgStock->id;
    }

    public function handle(LocationOrgStock $locationOrgStock): void
    {
        $costPerSku = $this->getCostPerSku($locationOrgStock->orgStock, now());

        $locationOrgStock->update([
            'value' => $locationOrgStock->quantity * $costPerSku
        ]);
    }
}
