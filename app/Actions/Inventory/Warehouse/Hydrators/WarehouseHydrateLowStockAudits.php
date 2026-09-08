<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 13 Aug 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Inventory\Warehouse\Hydrators;

use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class WarehouseHydrateLowStockAudits implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'hydrators-slave';

    public function getJobUniqueId(Warehouse $warehouse): string
    {
        return $warehouse->id;
    }

    public function handle(Warehouse $warehouse): void
    {
        $numberLowStockAudits = OrgStock::where('organisation_id', $warehouse->organisation_id)
            ->where('state', OrgStockStateEnum::ACTIVE)
            ->where('quantity_in_locations', '>', 0)
            ->where('quantity_in_locations', '<', $warehouse->getLowStockThreshold())
            ->count();

        $warehouse->stats()->update([
            'number_org_stocks_low_stock_audits' => $numberLowStockAudits
        ]);
    }
}
