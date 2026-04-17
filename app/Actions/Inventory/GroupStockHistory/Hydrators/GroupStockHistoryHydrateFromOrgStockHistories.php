<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Apr 2026 19:18:11 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\GroupStockHistory\Hydrators;

use App\Models\Inventory\GroupStockHistory;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupStockHistoryHydrateFromOrgStockHistories implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(?int $groupStockHistoryId): int
    {
        return $groupStockHistoryId ?? 'empty';
    }

    public function handle(?int $groupStockHistoryId): void
    {
        if (!$groupStockHistoryId) {
            return;
        }
        $groupStockHistory = GroupStockHistory::find($groupStockHistoryId);
        if (!$groupStockHistory) {
            return;
        }

        $stockData = DB::table('organisation_stock_histories')
            ->selectRaw('sum(grp_stock_value) as grp_stock_values')
            ->selectRaw('sum(number_org_stocks) as number_org_stocks')
            ->selectRaw('sum(number_locations) as number_locations')
            ->selectRaw('sum(number_out_of_stock_org_stocks) as number_out_of_stock_org_stocks')
            ->selectRaw('sum(value_dormant_stock_1y) as grp_value_dormant_stock_1y')
            ->where('group_stock_history_id', $groupStockHistory->id)
            ->first();

        $grpStockValue = $stockData->grp_stock_values ?? 0;
        $dormantValue  = $stockData->grp_value_dormant_stock_1y ?? 0;

        $groupStockHistory->update([
            'grp_stock_value'                   => $grpStockValue,
            'number_org_stocks'                 => $stockData->number_org_stocks ?? 0,
            'number_locations'                  => $stockData->number_locations ?? 0,
            'number_out_of_stock_org_stocks'    => $stockData->number_out_of_stock_org_stocks ?? 0,
            'grp_value_dormant_stock_1y'        => $dormantValue,
            'percentage_value_dormant_stock_1y' => $grpStockValue > 0 ? round($dormantValue / $grpStockValue * 100, 2) : 0,
        ]);
    }


}
