<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The other half of WithStockHistoryArchiveRead, for the sweeps that rewrite stock history:
 * BackfillStockValuations, the post cost fix recalculation and the rollups they feed.
 *
 * Where a reader degrades to the operational database when the archive cannot be reached, a
 * sweep must not: quietly recalculating three years and leaving sixteen at their old values is
 * precisely the divergence this exists to prevent, and it would look like a clean run. So an
 * archive that is configured but unreachable aborts the sweep. An archive that is not configured
 * at all (a developer machine, the test suite) means there is nothing older to rewrite.
 */
trait WithStockHistoryArchiveWrite
{
    protected function stockHistoryWriteConnection(): ?string
    {
        if (!config('database.connections.archive.database')) {
            return null;
        }

        try {
            DB::connection('archive')->getPdo();
        } catch (Throwable $exception) {
            throw new Exception(
                'The archive database holds stock history older than the retention window and cannot be reached; '.
                'refusing to rewrite only the recent years: '.$exception->getMessage()
            );
        }

        return 'archive';
    }

    /**
     * One SKU's history across both databases, oldest first, each row carrying the connection it
     * has to be written back to. A day lives entirely on one side, so the two already sorted
     * streams are concatenated and re-sorted rather than merged row by row, and the replay walks
     * the result exactly as it walked a single query before.
     */
    protected function stockHistoriesAcrossArchive(int $orgStockId, string $fromDate, array $columns): Collection
    {
        $query = fn (string $readConnection) => DB::connection($readConnection)->table('org_stock_histories')
            ->select($columns)
            ->where('org_stock_id', $orgStockId)
            ->where('date', '>=', $fromDate)
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $histories = $query('aiku_no_sticky')->each(fn ($history) => $history->write_connection = null);

        $archiveConnection = $this->stockHistoryWriteConnection();
        if (!$archiveConnection) {
            return $histories;
        }

        $archived = $query($archiveConnection)
            ->each(fn ($history) => $history->write_connection = $archiveConnection);

        return $histories->concat($archived)
            ->sortBy([['date', 'asc'], ['id', 'asc']])
            ->values();
    }

    /**
     * @return array<int, string|null> the connections a sweep has to touch, operational first
     */
    protected function stockHistoryWriteConnections(): array
    {
        $archive = $this->stockHistoryWriteConnection();

        return $archive ? [null, $archive] : [null];
    }
}
