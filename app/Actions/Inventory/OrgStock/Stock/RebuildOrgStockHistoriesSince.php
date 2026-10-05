<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\Stock;

use App\Models\Inventory\OrgStock;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Past days are rebuilt at end of day so each row keeps that day's closing stock; today comes from the live levels.
 */
class RebuildOrgStockHistoriesSince
{
    use AsAction;

    public string $jobQueue = 'stock-history';

    public function handle(int $orgStockId, string $fromDate): void
    {
        $orgStock = OrgStock::find($orgStockId);
        if (!$orgStock) {
            return;
        }

        $today = Carbon::today();
        for ($date = Carbon::parse($fromDate)->startOfDay(); $date->lt($today); $date->addDay()) {
            CalculateOrgStockHistoricStockHistories::run($orgStock, $date->copy()->endOfDay());
        }

        CalculateOrgStockCurrentStockHistories::run($orgStockId);
    }
}
