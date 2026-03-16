<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Goods\TradeUnit\Hydrators;

use App\Models\Goods\TradeUnitTimeSeries;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class TradeUnitTimeSeriesHydrateNumberRecords implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(int $timeSeriesId): string
    {
        return $timeSeriesId;
    }

    public function handle(int $timeSeriesId): void
    {
        $timeSeries = TradeUnitTimeSeries::find($timeSeriesId);

        if (!$timeSeries) {
            return;
        }

        $count = $timeSeries->records()->count();

        $timeSeries->update([
            'number_records' => $count,
            'from' => $timeSeries->records()->min('from'),
            'to' => $timeSeries->records()->max('to'),
        ]);
    }
}
