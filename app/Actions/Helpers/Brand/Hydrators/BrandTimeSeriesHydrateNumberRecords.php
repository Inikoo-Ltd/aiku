<?php

namespace App\Actions\Helpers\Brand\Hydrators;

use App\Models\Helpers\BrandTimeSeries;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class BrandTimeSeriesHydrateNumberRecords implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(int $timeSeriesId): string
    {
        return $timeSeriesId;
    }

    public function handle(int $timeSeriesId): void
    {
        $timeSeries = BrandTimeSeries::find($timeSeriesId);

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
