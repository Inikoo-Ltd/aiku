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
use Illuminate\Contracts\Broadcasting\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class CalculateValueLocationOrgStock implements ShouldBeUnique
{
    use AsAction;
    use CalculatesOrgStockHistories;

    private LocationOrgStock $locationOrgStock;

    public string $jobQueue = 'urgent';

    public function getJobUniqueId(?int $locationOrgStockId): string
    {
        return (string)($locationOrgStockId ?? 'empty');
    }

    public function handle(?int $locationOrgStockId): void
    {
        if (!$locationOrgStockId) {
            return;
        }
        $locationOrgStock = LocationOrgStock::find($locationOrgStockId);
        if (!$locationOrgStock) {
            return;
        }

        $costPerSku = $this->getCostPerSku($locationOrgStock->orgStock, Carbon::now());

        $locationOrgStock->update([
            'value' => $locationOrgStock->quantity * $costPerSku
        ]);
    }
}
