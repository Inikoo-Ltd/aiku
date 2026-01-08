<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Helpers\Intervals;

use App\Actions\Goods\Stock\Hydrators\StockHydrateSalesIntervals;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Models\Goods\Stock;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessResetIntervalsStocks
{
    use AsAction;

    public string $jobQueue = 'default-long';

    public function handle(array $intervals, array $doPreviousPeriods): void
    {
        foreach (
            Stock::whereIn('state', [
                StockStateEnum::ACTIVE,
                StockStateEnum::DISCONTINUING
            ])->get() as $stock
        ) {
            StockHydrateSalesIntervals::dispatch(
                stock: $stock,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            )->delay(now()->addMinutes(15))->onQueue('sales');
        }

        foreach (
            Stock::whereNotIn('state', [
                StockStateEnum::ACTIVE,
                StockStateEnum::DISCONTINUING
            ])->get() as $stock
        ) {
            StockHydrateSalesIntervals::dispatch(
                stock: $stock,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            )->delay(now()->addMinutes(60))->onQueue('low-priority');
        }
    }
}
