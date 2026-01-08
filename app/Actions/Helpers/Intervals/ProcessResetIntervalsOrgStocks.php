<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Helpers\Intervals;

use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateSalesIntervals;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Models\Inventory\OrgStock;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessResetIntervalsOrgStocks
{
    use AsAction;

    public function handle(array $intervals, array $doPreviousPeriods): void
    {
        foreach (
            OrgStock::whereIn('state', [
                OrgStockStateEnum::ACTIVE,
                OrgStockStateEnum::DISCONTINUING
            ])->get() as $orgStock
        ) {
            OrgStockHydrateSalesIntervals::dispatch(
                stock: $orgStock,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            )->delay(now()->addMinutes(10))->onQueue('sales');
        }

        foreach (
            OrgStock::whereNotIn('state', [
                OrgStockStateEnum::ACTIVE,
                OrgStockStateEnum::DISCONTINUING
            ])->get() as $orgStock
        ) {
            OrgStockHydrateSalesIntervals::dispatch(
                stock: $orgStock,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            )->delay(now()->addMinutes(90))->onQueue('low-priority');
        }
    }
}
