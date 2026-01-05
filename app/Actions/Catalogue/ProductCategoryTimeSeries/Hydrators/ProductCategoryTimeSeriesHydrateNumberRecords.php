<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\ProductCategoryTimeSeries\Hydrators;

use App\Models\Catalogue\ProductCategoryTimeSeries;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ProductCategoryTimeSeriesHydrateNumberRecords implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(int $timeSeriesId): string
    {
        return $timeSeriesId;
    }

    public function handle(int $timeSeriesId): void
    {
        $timeSeries = ProductCategoryTimeSeries::find($timeSeriesId);

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
