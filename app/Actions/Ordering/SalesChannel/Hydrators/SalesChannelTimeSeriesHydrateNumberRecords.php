<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Ordering\SalesChannel\Hydrators;

use App\Models\Ordering\SalesChannelTimeSeries;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class SalesChannelTimeSeriesHydrateNumberRecords implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(int $timeSeriesId): string
    {
        return $timeSeriesId;
    }

    public function handle(int $timeSeriesId): void
    {
        $timeSeries = SalesChannelTimeSeries::find($timeSeriesId);

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
