<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 12 Aug 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\Stock;

use App\Actions\Traits\WithStockHistoryArchiveWrite;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The organisation totals are summed from the per SKU rows, which for days beyond the retention
 * window live in the archive. A day is never split across the two databases, so each organisation
 * day is summed wholly on one side: the operational days keep the single UPDATE ... FROM they
 * always had, and the archived days are aggregated remotely and written back onto the same
 * (never archived) organisation_stock_histories rows. The group statement then reads those and
 * needs no archive awareness at all.
 */
class RollUpStockValuations
{
    use AsAction;
    use WithStockHistoryArchiveWrite;

    public function handle(Organisation $organisation): void
    {
        $wacStartDate = Carbon::parse($organisation->wac_calculations_start_date)->format('Y-m-d');

        DB::statement('
            UPDATE organisation_stock_histories AS osh
            SET org_stock_wac_value = agg.org_wac, grp_stock_wac_value = agg.grp_wac, org_stock_fifo_value = agg.org_fifo, grp_stock_fifo_value = agg.grp_fifo
            FROM (
                SELECT organisation_stock_history_id, sum(org_stock_wac_value) AS org_wac, sum(grp_stock_wac_value) AS grp_wac, sum(org_stock_fifo_value) AS org_fifo, sum(grp_stock_fifo_value) AS grp_fifo
                FROM org_stock_histories
                WHERE organisation_id = ? AND date >= ?
                GROUP BY organisation_stock_history_id
            ) AS agg
            WHERE agg.organisation_stock_history_id = osh.id
        ', [$organisation->id, $wacStartDate]);

        $this->rollUpArchivedDays($organisation, $wacStartDate);

        DB::statement('
            UPDATE group_stock_histories AS gsh
            SET grp_stock_wac_value = agg.grp_wac, grp_stock_fifo_value = agg.grp_fifo
            FROM (
                SELECT group_stock_history_id, sum(grp_stock_wac_value) AS grp_wac, sum(grp_stock_fifo_value) AS grp_fifo
                FROM organisation_stock_histories
                WHERE group_id = ? AND date >= ?
                GROUP BY group_stock_history_id
            ) AS agg
            WHERE agg.group_stock_history_id = gsh.id
        ', [$organisation->group_id, $wacStartDate]);
    }

    private function rollUpArchivedDays(Organisation $organisation, string $wacStartDate): void
    {
        $archiveConnection = $this->stockHistoryWriteConnection();

        if (!$archiveConnection) {
            return;
        }

        $aggregates = DB::connection($archiveConnection)->table('org_stock_histories')
            ->selectRaw('organisation_stock_history_id, sum(org_stock_wac_value) AS org_wac, sum(grp_stock_wac_value) AS grp_wac, sum(org_stock_fifo_value) AS org_fifo, sum(grp_stock_fifo_value) AS grp_fifo')
            ->where('organisation_id', $organisation->id)
            ->where('date', '>=', $wacStartDate)
            ->whereNotNull('organisation_stock_history_id')
            ->groupBy('organisation_stock_history_id')
            ->get();

        foreach ($aggregates as $aggregate) {
            DB::table('organisation_stock_histories')
                ->where('id', $aggregate->organisation_stock_history_id)
                ->update([
                    'org_stock_wac_value'  => $aggregate->org_wac,
                    'grp_stock_wac_value'  => $aggregate->grp_wac,
                    'org_stock_fifo_value' => $aggregate->org_fifo,
                    'grp_stock_fifo_value' => $aggregate->grp_fifo,
                ]);
        }
    }

    public function getCommandSignature(): string
    {
        return 'org_stock:backfill_valuations_rollup {organisation : Organisation slug}';
    }

    public function asCommand(Command $command): int
    {
        $organisation = Organisation::where('slug', $command->argument('organisation'))->firstOrFail();

        if (!$organisation->wac_calculations_start_date) {
            $command->error("Organisation $organisation->slug has no wac_calculations_start_date set");

            return 1;
        }

        $this->handle($organisation);
        $command->info('Organisation and group stock histories rolled up');

        return 0;
    }
}
