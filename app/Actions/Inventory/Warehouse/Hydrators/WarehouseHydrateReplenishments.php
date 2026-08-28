<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 13 Aug 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Inventory\Warehouse\Hydrators;

use App\Actions\Inventory\OrgStock\WithOrgStockReplenishments;
use App\Models\Inventory\Warehouse;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class WarehouseHydrateReplenishments implements ShouldBeUnique
{
    use AsAction;
    use WithOrgStockReplenishments;

    public string $jobQueue = 'hydrators-slave';

    public function getJobUniqueId(Warehouse $warehouse): string
    {
        return $warehouse->id;
    }

    public function handle(Warehouse $warehouse): void
    {
        $warehouse->stats()->update([
            'number_org_stocks_replenishments_wholesale'    => $this->countReplenishments($warehouse->organisation, self::SCOPE_WHOLESALE),
            'number_org_stocks_replenishments_dropshipping' => $this->countReplenishments($warehouse->organisation, self::SCOPE_DROPSHIPPING),
        ]);
    }
}
