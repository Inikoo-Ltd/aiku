<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 10 Aug 2026 00:30:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\AgentSupplierPurchaseOrder;

use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class HousekeepAgentSupplierPurchaseOrders
{
    use AsAction;

    public string $commandSignature = 'supply-chain:housekeep_agent_supplier_purchase_orders {--days=365 : Flag stalled orders older than this many days} {--undo : Remove the housekeeping flag instead}';

    /**
     * Legacy stalled orders never got their delivery states closed in Aurora; flagging
     * them (instead of inventing a terminal state we cannot know) keeps the control
     * board focused on live signal while leaving the records untouched and reversible.
     */
    public function handle(int $days = 365, bool $undo = false): int
    {
        if ($undo) {
            return DB::table('agent_supplier_purchase_orders')
                ->whereRaw("(data -> 'housekeeping') IS NOT NULL")
                ->update(['data' => DB::raw("data - 'housekeeping'")]);
        }

        return DB::table('agent_supplier_purchase_orders')
            ->whereNotIn('delivery_state', ['received', 'checked', 'placed', 'cancelled'])
            ->where('state', '!=', 'cancelled')
            ->where('date', '<', now()->subDays($days))
            ->whereRaw("(data -> 'housekeeping') IS NULL")
            ->update([
                'data' => DB::raw(
                    "jsonb_set(data, '{housekeeping}', jsonb_build_object('flagged_at', to_char(now(), 'YYYY-MM-DD\"T\"HH24:MI:SSOF'), 'reason', 'legacy stalled order, pre-aiku'))"
                ),
            ]);
    }

    public function asCommand($command): int
    {
        $flagged = $this->handle((int)$command->option('days'), (bool)$command->option('undo'));

        $command->info(($command->option('undo') ? 'Unflagged: ' : 'Flagged: ').$flagged);

        return 0;
    }
}
