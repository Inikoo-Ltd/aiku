<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Goods\StockFamily\Hydrators;

use App\Models\Goods\StockFamilyTimeSeries;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class StockFamilyTimeSeriesHydrateNumberRecords implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(int $timeSeriesId): string
    {
        return $timeSeriesId;
    }

    public function handle(int $timeSeriesId): void
    {
        $timeSeries = StockFamilyTimeSeries::find($timeSeriesId);

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
