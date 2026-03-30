<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 31 Mar 2026 01:15:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\Stock;

use App\Actions\Maintenance\Inventory\OrgStockMovement\RepairOrgStockMovements;
use App\Models\Inventory\OrgStock;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class CalculateOrgStockHistoryAsync implements ShouldBeUnique
{
    use AsAction;


    public function getCommandSignature(): string
    {
        return 'calculate:org_stock_history_async {--f|fix_movements}';
    }

    public function asCommand(Command $command): int
    {
        $fixMovements = $command->option('fix_movements');
        if ($fixMovements) {
            $command->info('Fixing movements');
        }



        /** @var OrgStock $orgStock */
        foreach (OrgStock::orderBy('id')->get() as $orgStock) {
            if ($fixMovements) {
                RepairOrgStockMovements::run($orgStock, $command);
            }
            $command->info('Processing '.$orgStock->slug.' ('.$orgStock->id.')');
            CalculateOrgStockHistory:dispatch($orgStock);
        }

        return 0;
    }

}
