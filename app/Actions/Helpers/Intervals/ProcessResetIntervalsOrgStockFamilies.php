<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Helpers\Intervals;

use App\Actions\Inventory\OrgStockFamily\Hydrators\OrgStockFamilyHydrateSalesIntervals;
use App\Enums\Inventory\OrgStockFamily\OrgStockFamilyStateEnum;
use App\Models\Inventory\OrgStockFamily;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessResetIntervalsOrgStockFamilies
{
    use AsAction;

    public string $commandSignature = 'aiku:process-reset-intervals-org-stock-families';

    public function handle(array $intervals = [], array $doPreviousPeriods = []): void
    {
        foreach (
            OrgStockFamily::whereIn(
                'state',
                [
                    OrgStockFamilyStateEnum::ACTIVE,
                    OrgStockFamilyStateEnum::DISCONTINUING
                ]
            )->get() as $orgStockFamily
        ) {
            OrgStockFamilyHydrateSalesIntervals::dispatch(
                stockFamily: $orgStockFamily,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            )->delay(now()->addMinutes(2))->onQueue('sales');
        }

        foreach (
            OrgStockFamily::whereNotIn(
                'state',
                [
                    OrgStockFamilyStateEnum::ACTIVE,
                    OrgStockFamilyStateEnum::DISCONTINUING
                ]
            )->get() as $orgStockFamily
        ) {
            OrgStockFamilyHydrateSalesIntervals::dispatch(
                stockFamily: $orgStockFamily,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            )->delay(now()->addMinutes(45))->onQueue('low-priority');
        }
    }
}
