<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 Mar 2024 12:24:25 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\StockFamily\Hydrators;

use App\Actions\Goods\StockFamily\UpdateStockFamily;
use App\Actions\Traits\WithEnumStats;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Enums\Goods\StockFamily\StockFamilyStateEnum;
use App\Models\Goods\Stock;
use App\Models\Goods\StockFamily;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class StockFamilyHydrateStocks implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;


    public function getJobUniqueId(StockFamily $stockFamily): string
    {
        return $stockFamily->id;
    }

    public function handle(StockFamily $stockFamily): void
    {
        $stats = [
            'number_stocks' => $stockFamily->stocks()->count(),
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'stocks',
                field: 'state',
                enum: StockStateEnum::class,
                models: Stock::class,
                where: function ($q) use ($stockFamily) {
                    $q->where('stock_family_id', $stockFamily->id);
                }
            )
        );

        $stats['number_current_stocks'] = Arr::get($stats, 'number_stocks_state_active', 0) + Arr::get($stats, 'number_stocks_state_discontinuing', 0);

        UpdateStockFamily::make()->action(
            $stockFamily,
            [
                'state' => $this->getStockFamilyState($stats)
            ]
        );


        $stockFamily->stats()->update($stats);
    }

    public function getStockFamilyState($stats): StockFamilyStateEnum
    {
        if ($stats['number_stocks'] == 0) {
            return StockFamilyStateEnum::IN_PROCESS;
        }

        if (Arr::get($stats, 'number_stocks_state_active', 0) > 0) {
            return StockFamilyStateEnum::ACTIVE;
        }

        if (Arr::get($stats, 'number_stocks_state_discontinuing', 0) > 0) {
            return StockFamilyStateEnum::DISCONTINUING;
        }

        if (Arr::get($stats, 'number_stocks_state_in_process', 0) > 0) {
            return StockFamilyStateEnum::IN_PROCESS;
        }

        return StockFamilyStateEnum::DISCONTINUED;

    }


}
