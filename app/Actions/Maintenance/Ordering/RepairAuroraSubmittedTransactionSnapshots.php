<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 31 Jul 2026 23:55:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Ordering;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairAuroraSubmittedTransactionSnapshots
{
    use AsAction;

    /**
     * Aurora fetched in-basket transactions ('In Process') with state=submitted, so SubmitOrder's
     * snapshot loop (which only takes state=creating lines) skips them and they go through
     * fulfilment without submitted_* data, leaving them unprotected against post-submission
     * discount recalculations. Two repairs:
     *
     * 1. Lines in orders still in basket: reset state to creating so SubmitOrder snapshots them.
     * 2. Lines in orders already past submission: backfill the snapshot from current values,
     *    the best remaining record of what the customer agreed to at submission.
     */
    public string $commandSignature = 'repair:aurora_submitted_transaction_snapshots {--d|dry_run : Report counts without updating}';

    public function asCommand(Command $command): int
    {
        $dryRun = (bool)$command->option('dry_run');

        $basketLinesCount = DB::table('transactions')
            ->join('orders', 'orders.id', '=', 'transactions.order_id')
            ->where('orders.state', 'creating')
            ->where('transactions.state', 'submitted')
            ->where('transactions.status', 'creating')
            ->whereNotNull('transactions.source_id')
            ->whereNull('transactions.deleted_at')
            ->count();

        $command->info("Basket lines to reset to state=creating: $basketLinesCount");

        if (!$dryRun) {
            $totalReset = 0;
            do {
                $affected = DB::update("
                    UPDATE transactions
                    SET state = 'creating', updated_at = NOW()
                    WHERE id IN (
                        SELECT t.id
                        FROM transactions t
                        JOIN orders o ON o.id = t.order_id
                        WHERE o.state = 'creating'
                          AND t.state = 'submitted'
                          AND t.status = 'creating'
                          AND t.source_id IS NOT NULL
                          AND t.deleted_at IS NULL
                        LIMIT 1000
                    )
                ");
                $totalReset += $affected;
                if ($affected > 0 && $totalReset % 50000 < 1000) {
                    $command->line("  reset so far: $totalReset");
                }
            } while ($affected > 0);
            $command->info("Basket lines reset: $totalReset");
        }

        $inFlightOrderStates = "'submitted','in_warehouse','handling','handling_blocked','picked','packing','packed'";

        $unsnapshottedCount = DB::table('transactions')
            ->join('orders', 'orders.id', '=', 'transactions.order_id')
            ->whereIn('orders.state', ['submitted', 'in_warehouse', 'handling', 'handling_blocked', 'picked', 'packing', 'packed'])
            ->whereNull('transactions.submitted_at')
            ->whereNotIn('transactions.state', ['creating', 'cancelled'])
            ->whereNull('transactions.deleted_at')
            ->count();

        $command->info("In-flight lines to backfill snapshot: $unsnapshottedCount");

        if (!$dryRun) {
            $backfilled = DB::update("
                UPDATE transactions t
                SET submitted_at                = o.submitted_at,
                    submitted_quantity_ordered  = t.quantity_ordered,
                    submitted_gross_amount      = t.gross_amount,
                    submitted_net_amount        = t.net_amount,
                    submitted_discount_factor   = COALESCE(t.current_discount_factor, 1),
                    submitted_offers_data       = CASE
                                                      WHEN jsonb_typeof(t.offers_data::jsonb) = 'object' THEN t.offers_data::jsonb
                                                      ELSE '{}'::jsonb
                                                  END,
                    has_discount_when_submitted = COALESCE(t.current_discount_factor, 1) < 1,
                    updated_at                  = NOW()
                FROM orders o
                WHERE o.id = t.order_id
                  AND o.state IN ($inFlightOrderStates)
                  AND t.submitted_at IS NULL
                  AND t.state NOT IN ('creating', 'cancelled')
                  AND t.deleted_at IS NULL
            ");
            $command->info("In-flight lines backfilled: $backfilled");
        }

        /**
         * Race sweep: step 1 can flip a line to creating while SubmitOrder is mid-flight on the
         * same order (it has already read the creating lines to snapshot, but not yet committed
         * the state change). Such a line ends as state=creating inside a submitted order, which
         * no other flow ever produces for Aurora lines. Restore what SubmitOrder would have set:
         * submitted state plus a snapshot from current values. Safe to re-run anytime.
         */
        $raceVictimsCount = DB::table('transactions')
            ->join('orders', 'orders.id', '=', 'transactions.order_id')
            ->whereIn('orders.state', ['submitted', 'in_warehouse', 'handling', 'handling_blocked', 'picked', 'packing', 'packed'])
            ->where('transactions.state', 'creating')
            ->whereNotNull('transactions.source_id')
            ->whereNull('transactions.deleted_at')
            ->count();

        $command->info("Race-victim lines to restore: $raceVictimsCount");

        if (!$dryRun && $raceVictimsCount > 0) {
            $restored = DB::update("
                UPDATE transactions t
                SET state                       = 'submitted',
                    status                      = 'processing',
                    submitted_at                = o.submitted_at,
                    submitted_quantity_ordered  = t.quantity_ordered,
                    submitted_gross_amount      = t.gross_amount,
                    submitted_net_amount        = t.net_amount,
                    submitted_discount_factor   = COALESCE(t.current_discount_factor, 1),
                    submitted_offers_data       = CASE
                                                      WHEN jsonb_typeof(t.offers_data::jsonb) = 'object' THEN t.offers_data::jsonb
                                                      ELSE '{}'::jsonb
                                                  END,
                    has_discount_when_submitted = COALESCE(t.current_discount_factor, 1) < 1,
                    updated_at                  = NOW()
                FROM orders o
                WHERE o.id = t.order_id
                  AND o.state IN ($inFlightOrderStates)
                  AND t.state = 'creating'
                  AND t.source_id IS NOT NULL
                  AND t.deleted_at IS NULL
            ");
            $command->info("Race-victim lines restored: $restored");
        }

        return 0;
    }
}
