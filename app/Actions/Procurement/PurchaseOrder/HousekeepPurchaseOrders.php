<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 22 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder;

use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

class HousekeepPurchaseOrders
{
    use AsAction;

    public string $commandSignature = 'procurement:housekeep_purchase_orders {--days=365 : Flag open orders older than this many days that never finished} {--undo : Remove the housekeeping flag instead}';

    /**
     * Legacy open orders never got closed in Aurora; flagging them keeps the PO journey
     * board on live signal while leaving the records untouched and reversible.
     */
    public function handle(int $days = 365, bool $undo = false): int
    {
        if ($undo) {
            return DB::table('purchase_orders')
                ->whereRaw("(data -> 'housekeeping') IS NOT NULL")
                ->update(['data' => DB::raw("data - 'housekeeping'")]);
        }

        return DB::table('purchase_orders')
            ->where(function ($query) {
                $query->where(function ($open) {
                    $open->whereIn('state', ['in_process', 'submitted', 'confirmed'])
                        ->whereNotIn('delivery_state', ['received', 'checked', 'placed']);
                })->orWhere(function ($settled) {
                    $settled->where('state', 'settled')
                        ->whereIn('delivery_state', ['received', 'checked']);
                });
            })
            ->where('created_at', '<', now()->subDays($days))
            ->whereRaw("(data -> 'housekeeping') IS NULL")
            ->update([
                'data' => DB::raw(
                    "jsonb_set(coalesce(data, '{}'::jsonb), '{housekeeping}', jsonb_build_object('flagged_at', to_char(now(), 'YYYY-MM-DD\"T\"HH24:MI:SSOF'), 'reason', 'legacy stalled order, pre-aiku'))"
                ),
            ]);
    }

    public function asCommand($command): int
    {
        Nightwatch::dontSample();

        $flagged = $this->handle((int) $command->option('days'), (bool) $command->option('undo'));

        $command->info(($command->option('undo') ? 'Unflagged: ' : 'Flagged: ').$flagged);

        return 0;
    }
}
