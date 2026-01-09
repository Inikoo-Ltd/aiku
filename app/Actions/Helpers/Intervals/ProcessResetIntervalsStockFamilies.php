<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Helpers\Intervals;

use App\Actions\Goods\StockFamily\Hydrators\StockFamilyHydrateSalesIntervals;
use App\Enums\Goods\StockFamily\StockFamilyStateEnum;
use App\Models\Goods\StockFamily;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessResetIntervalsStockFamilies
{
    use AsAction;

    public string $jobQueue = 'default-long';
    public string $commandSignature = 'aiku:process-reset-intervals-stock-families';

    public function handle(array $intervals = [], array $doPreviousPeriods = []): void
    {
        foreach (
            StockFamily::whereIn(
                'state',
                [
                    StockFamilyStateEnum::ACTIVE,
                    StockFamilyStateEnum::DISCONTINUING
                ]
            )->get() as $stockFamily
        ) {
            StockFamilyHydrateSalesIntervals::dispatch(
                stockFamily: $stockFamily,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            )->delay(now()->addMinutes(2))->onQueue('sales');
        }

        foreach (
            StockFamily::whereNotIn(
                'state',
                [
                    StockFamilyStateEnum::ACTIVE,
                    StockFamilyStateEnum::DISCONTINUING
                ]
            )->get() as $stockFamily
        ) {
            StockFamilyHydrateSalesIntervals::dispatch(
                stockFamily: $stockFamily,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            )->delay(now()->addMinutes(30))->onQueue('low-priority');
        }
    }
}
