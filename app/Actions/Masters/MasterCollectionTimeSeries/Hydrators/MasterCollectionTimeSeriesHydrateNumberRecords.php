<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Jan 2026 02:54:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterCollectionTimeSeries\Hydrators;

use App\Models\Masters\MasterCollectionTimeSeries;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterCollectionTimeSeriesHydrateNumberRecords implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(int $timeSeriesId): string
    {
        return $timeSeriesId;
    }

    public function handle(int $timeSeriesId): void
    {
        $timeSeries = MasterCollectionTimeSeries::find($timeSeriesId);

        if (!$timeSeries) {
            return;
        }

        $count = $timeSeries->records()->count();

        $timeSeries->update([
            'number_records' => $count,
            'from'           => $timeSeries->records()->min('from'),
            'to'             => $timeSeries->records()->max('to'),
        ]);
    }
}
