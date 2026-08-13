<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 12 Aug 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Inventory\OrgStockMovement;

use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairOrgStockMovementCostPerSkuFromOrgAmount
{
    use AsAction;

    public function getCommandSignature(): string
    {
        return 'org_stock_movement:repair_cost_per_sku_from_org_amount';
    }

    public function asCommand(Command $command): int
    {
        $updated = DB::table('org_stock_movements')
            ->where('type', OrgStockMovementTypeEnum::PURCHASE->value)
            ->whereNull('cost_per_sku')
            ->where('org_amount', '>', 0)
            ->where('quantity', '>', 0)
            ->update(['cost_per_sku' => DB::raw('org_amount / quantity')]);

        $command->info("Updated $updated purchase movements");

        return 0;
    }
}
