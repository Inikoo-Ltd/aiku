<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 14 Jun 2025 17:31:52 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnit\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Models\Goods\TradeUnit;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class TradeUnitsHydrateOrgStocks implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(TradeUnit $tradeUnit): string
    {
        return $tradeUnit->id;
    }

    public function handle(TradeUnit $tradeUnit): void
    {

        $stats = [
            'number_org_stocks' => $tradeUnit->orgStocks()->count()
        ];

        $count = DB::table('model_has_trade_units')
            ->leftJoin('org_stocks', 'org_stocks.id', '=', 'model_has_trade_units.model_id')
            ->where('trade_unit_id', $tradeUnit->id)
            ->where('model_type', 'OrgStock')
            ->selectRaw("org_stocks.state as state, count(*) as total")
            ->groupBy('org_stocks.state')
            ->pluck('total', 'state')->all();
        foreach (OrgStockStateEnum::cases() as $case) {
            $stats["number_org_stocks_state_".$case->snake()] = Arr::get($count, $case->value, 0);
        }


        $stats['number_current_org_stocks'] = Arr::get($stats, 'number_org_stocks_state_active', 0) +
            Arr::get($stats, 'number_org_stocks_state_discontinuing', 0);


        $tradeUnitStats = $tradeUnit->stats;

        $tradeUnitStats->update($stats);
        $changed = Arr::except($tradeUnitStats->getChanges(), ['updated_at', 'last_fetched_at']);
        if (count($changed) > 0) {
            TradeUnitHydrateStatus::run($tradeUnit);
        }






    }

}
