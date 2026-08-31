<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Models\Inventory\OrganisationStockHistory;
use Closure;
use Generator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reads per SKU stock history back from the archive database transparently. ArchiveStockHistories
 * mirrors org_stocks, locations and organisation_stock_histories there, so an archived day answers
 * exactly the same query as a live one and the callers only ever choose a connection.
 *
 * A day lives entirely on one side or the other, never split, which is what makes the range
 * readers a plain concatenation of two already sorted streams rather than a real merge.
 *
 * The archive is on another server and is never allowed to break a page: anything that fails
 * reaching it is logged and the operational database answers alone.
 */
trait WithStockHistoryArchiveRead
{
    protected function stockHistoryArchiveConnection(): ?string
    {
        return config('database.connections.archive.database') ? 'archive' : null;
    }

    protected function stockHistoryRetentionCutoff(): Carbon
    {
        return now()->subMonths(config('archive.stock_history_retention_months'))->startOfDay();
    }

    /**
     * For the readers that show a single day. The cheap date test comes first so a day inside the
     * retention window never pays for a probe, and the probe itself keeps days that were left in
     * place (the monthly snapshots) on the operational database.
     */
    protected function stockHistoryDayConnection(OrganisationStockHistory $organisationStockHistory): ?string
    {
        if (Carbon::parse($organisationStockHistory->date)->gte($this->stockHistoryRetentionCutoff())) {
            return null;
        }

        if (DB::table('org_stock_histories')->where('organisation_stock_history_id', $organisationStockHistory->id)->exists()) {
            return null;
        }

        $connection = $this->stockHistoryArchiveConnection();
        if (!$connection) {
            return null;
        }

        try {
            return DB::connection($connection)
                ->table('org_stock_histories')
                ->where('organisation_stock_history_id', $organisationStockHistory->id)
                ->exists() ? $connection : null;
        } catch (Throwable $exception) {
            Log::warning('Archive database unreachable, serving live stock history only: '.$exception->getMessage());

            return null;
        }
    }

    protected function stockHistoryRangeNeedsArchive(?string $fromDate): bool
    {
        if (!$this->stockHistoryArchiveConnection()) {
            return false;
        }

        return $fromDate === null || Carbon::parse($fromDate)->lt($this->stockHistoryRetentionCutoff());
    }

    /**
     * @param Closure(?string): \Illuminate\Database\Query\Builder $queryFactory
     */
    protected function stockHistoryArchiveRows(Closure $queryFactory): Collection
    {
        try {
            return collect($queryFactory($this->stockHistoryArchiveConnection())->get());
        } catch (Throwable $exception) {
            Log::warning('Archive database unreachable, serving live stock history only: '.$exception->getMessage());

            return collect();
        }
    }

    /**
     * Streams both sides for the exports, newest first: the operational database holds every date
     * from the cutoff onwards plus the monthly snapshots below it, so its rows are yielded until
     * they fall past an archived date and the archive takes over. Neither side is ever loaded
     * whole, because an organisation wide range can run to millions of rows.
     *
     * @param Closure(?string): \Illuminate\Database\Query\Builder $queryFactory
     */
    protected function stockHistoryRowsNewestFirst(Closure $queryFactory, ?string $fromDate): Generator
    {
        $live = $queryFactory(null)->cursor();

        if (!$this->stockHistoryRangeNeedsArchive($fromDate)) {
            yield from $live;

            return;
        }

        try {
            $archived = $queryFactory($this->stockHistoryArchiveConnection())->cursor()->getIterator();
        } catch (Throwable $exception) {
            Log::warning('Archive database unreachable, serving live stock history only: '.$exception->getMessage());
            yield from $live;

            return;
        }

        $archived->rewind();

        foreach ($live as $row) {
            while ($archived->valid() && $archived->current()->date > $row->date) {
                yield $archived->current();
                $archived->next();
            }
            yield $row;
        }

        while ($archived->valid()) {
            yield $archived->current();
            $archived->next();
        }
    }
}
