<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 13 Aug 2026 22:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Inventory\OrgStockMovement;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The first repair run predated the corruption-window insight and overwrote movements dated
 * before it from old stock_delivery_items rows, which are not trustworthy; those movements
 * matched Aurora exactly and must go back to their snapshotted values.
 */
class RestorePreCorruptionOrgStockMovements
{
    use AsAction;

    public function getCommandSignature(): string
    {
        return 'org_stock_movement:restore_pre_corruption_movements {--dry-run : Report what would be restored without writing}';
    }

    public function asCommand(Command $command): int
    {
        $rows = DB::select('
            select m.id, m.org_stock_id, o.slug as organisation_slug, s.date,
                m.org_amount as current_amount, s.org_amount as snapshot_amount
            from org_stock_movements m
            join org_stock_movements_pre_costfix s on s.id = m.id
            join organisations o on o.id = m.organisation_id
            where s.date < ?
            and m.org_amount is distinct from s.org_amount
            order by o.slug, s.date
        ', [RepairOrgStockMovementCostFromStockDeliveryItems::CORRUPTION_WINDOW_START]);

        $command->table(
            ['movement', 'org', 'org_stock', 'date', 'current amount', 'restoring to'],
            array_map(fn ($row) => [
                $row->id,
                $row->organisation_slug,
                $row->org_stock_id,
                substr($row->date, 0, 10),
                $row->current_amount,
                $row->snapshot_amount,
            ], $rows)
        );
        $command->info(count($rows).' movements to restore');

        if ($command->option('dry-run')) {
            $command->info('Dry run: no changes written');

            return 0;
        }

        if (!$rows) {
            return 0;
        }

        DB::statement('
            update org_stock_movements m
            set org_amount = s.org_amount,
                grp_amount = s.grp_amount,
                cost_per_sku = s.cost_per_sku,
                cost_status = s.cost_status
            from org_stock_movements_pre_costfix s
            where s.id = m.id and s.date < ?
        ', [RepairOrgStockMovementCostFromStockDeliveryItems::CORRUPTION_WINDOW_START]);

        $orgStockIds = array_unique(array_column($rows, 'org_stock_id'));
        foreach ($orgStockIds as $orgStockId) {
            RecalculateOrgStockHistoriesPostCostFix::dispatch($orgStockId, fullWalk: true);
        }

        $command->info('Restored '.count($rows).' movements, dispatched '.count($orgStockIds).' history recalculation jobs');
        $command->info('Run org_stock_movement:recalculate_histories_post_costfix_rollup per organisation once the queue drains');

        return 0;
    }
}
