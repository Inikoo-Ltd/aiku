<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 06 Jul 2025 11:24:02 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnit\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Models\Goods\TradeUnit;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class TradeUnitsHydrateStocks implements ShouldBeUnique
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
            'number_stocks' => $tradeUnit->stocks()->count()
        ];

        $count = DB::table('model_has_trade_units')
            ->leftJoin('stocks', 'stocks.id', '=', 'model_has_trade_units.model_id')
            ->where('trade_unit_id', $tradeUnit->id)
            ->where('model_type', 'Stock')
            ->selectRaw("stocks.state as state, count(*) as total")
            ->groupBy('stocks.state')
            ->pluck('total', 'state')->all();
        foreach (StockStateEnum::cases() as $case) {
            $stats["number_stocks_state_".$case->snake()] = Arr::get($count, $case->value, 0);
        }

        $stats['number_current_stocks'] = Arr::get($stats, 'number_stocks_state_active', 0) +
            Arr::get($stats, 'number_stocks_state_discontinuing', 0);

        $tradeUnitStats = $tradeUnit->stats;

        $tradeUnitStats->update($stats);
        $changed = Arr::except($tradeUnitStats->getChanges(), ['updated_at', 'last_fetched_at']);
        if (count($changed) > 0) {
            TradeUnitHydrateStatus::run($tradeUnit);
        }
    }

    public function getCommandSignature(): string
    {
        return 'hydrate:trade-units-stocks';
    }

    public function asCommand()
    {
        $tradeUnit = TradeUnit::where('slug', 'jbb-01')->first();
        $this->handle($tradeUnit);
    }
}
