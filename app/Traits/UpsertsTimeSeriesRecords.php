<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 30 Jul 2026 09:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;

trait UpsertsTimeSeriesRecords
{
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
