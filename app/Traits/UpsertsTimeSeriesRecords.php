<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 30 Jul 2026 09:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Traits;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

trait UpsertsTimeSeriesRecords
{
    /**
     * Daily, weekly and monthly records are stored sparsely: a period with nothing in it is deleted
     * instead of stored as a row of zeros, which is what keeps those partitions small. Quarterly and
     * yearly stay dense because the showcase charts read them record by record.
     *
     * Periods inside the window that are not in $rows are deleted either way, since the upsert alone
     * would leave a period that lost its invoices holding its old non zero value.
     *
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string> $uniqueBy
     */
    protected function syncTimeSeriesRecords(Model $timeSeries, array $rows, array $uniqueBy, string $from, string $to): void
    {
        if ($this->timeSeriesIsSparse($timeSeries->frequency)) {
            $rows = array_values(array_filter($rows, fn (array $row) => $this->timeSeriesRowHasActivity($row, $uniqueBy)));
        }

        $timeSeries->records()
            ->where('frequency', $timeSeries->frequency->singleLetter())
            ->where('from', '>=', $from)
            ->where('from', '<=', $to)
            ->when($rows !== [], fn ($query) => $query->whereNotIn('period', array_column($rows, 'period')))
            ->delete();

        $this->upsertTimeSeriesRecords($timeSeries, $rows, $uniqueBy);
    }

    protected function timeSeriesIsSparse(TimeSeriesFrequencyEnum $frequency): bool
    {
        return !in_array($frequency, [TimeSeriesFrequencyEnum::QUARTERLY, TimeSeriesFrequencyEnum::YEARLY]);
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $uniqueBy
     */
    protected function timeSeriesRowHasActivity(array $row, array $uniqueBy): bool
    {
        $metrics = Arr::except($row, [...$uniqueBy, 'period', 'frequency', 'from', 'to']);

        foreach ($metrics as $value) {
            if ($value !== null && $value != 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Bulk upsert collected period rows in chunks instead of one updateOrCreate per period.
     * Requires the {table}_nk_unique index on the natural key so ON CONFLICT can match.
     *
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string> $uniqueBy
     */
    protected function upsertTimeSeriesRecords(Model $timeSeries, array $rows, array $uniqueBy): void
    {
        if ($rows === []) {
            return;
        }

        $updateColumns = array_values(array_diff(array_keys($rows[0]), $uniqueBy));

        foreach (array_chunk($rows, 500) as $chunk) {
            $timeSeries->records()->upsert($chunk, $uniqueBy, $updateColumns);
        }
    }
}
