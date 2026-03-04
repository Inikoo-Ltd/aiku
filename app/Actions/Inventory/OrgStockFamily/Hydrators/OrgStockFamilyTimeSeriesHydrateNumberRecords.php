<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 04 Mar 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Inventory\OrgStockFamily\Hydrators;

use App\Models\Inventory\OrgStockFamilyTimeSeries;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrgStockFamilyTimeSeriesHydrateNumberRecords implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(int $timeSeriesId): string
    {
        return $timeSeriesId;
    }

    public function handle(int $timeSeriesId): void
    {
        $timeSeries = OrgStockFamilyTimeSeries::find($timeSeriesId);

        if (!$timeSeries) {
            return;
        }

        $timeSeries->update([
            'number_records' => $timeSeries->records()->count(),
            'from'           => $timeSeries->records()->min('from'),
            'to'             => $timeSeries->records()->max('to'),
        ]);
    }
}
