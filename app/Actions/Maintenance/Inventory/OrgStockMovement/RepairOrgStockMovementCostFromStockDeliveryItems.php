<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 13 Aug 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Inventory\OrgStockMovement;

use App\Enums\Inventory\OrgStockMovement\OrgStockMovementCostStatusEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairOrgStockMovementCostFromStockDeliveryItems
{
    use AsAction;

    public const float AMOUNT_RATIO_THRESHOLD = 5;
    public const float QUANTITY_RATIO_MIN = 0.5;
    public const float QUANTITY_RATIO_MAX = 2;

    /**
     * The org_amount corruption came from refetches recomputing quantity * value_in_locations,
     * and only recently fetched movements hit that path; older movements match Aurora exactly
     * and old stock_delivery_items are not a trustworthy source to overwrite them from.
     */
    public const string CORRUPTION_WINDOW_START = '2026-06-01';

    public function getCommandSignature(): string
    {
        return 'org_stock_movement:repair_cost_from_stock_delivery_items {organisation?} {--dry-run : Report what would change without writing}';
    }

    public function asCommand(Command $command): int
    {
        $dryRun = $command->option('dry-run');

        $query = "
            select * from (
                select distinct on (m.id)
                    m.id,
                    m.organisation_id,
                    m.org_stock_id,
                    m.date,
                    m.quantity      as movement_quantity,
                    m.org_amount    as movement_amount,
                    m.cost_per_sku  as movement_cost_per_sku,
                    sdi.net_amount    as delivery_amount,
                    sdi.unit_quantity as delivery_quantity,
                    sd.reference      as delivery_reference,
                    o.slug            as organisation_slug
                from org_stock_movements m
                join organisations o on o.id = m.organisation_id
                join stock_deliveries sd
                    on sd.source_id = m.organisation_id::text || ':' || substring(m.note from 'delivery/([0-9]+)')
                join stock_delivery_items sdi
                    on sdi.stock_delivery_id = sd.id and sdi.org_stock_id = m.org_stock_id
                where m.type = ?
                    and m.date >= ?
                    and m.note ~ 'delivery/[0-9]+'
                    and m.quantity > 0
                    and sdi.net_amount > 0
                    and sdi.unit_quantity > 0
                order by m.id, sdi.id
            ) flagged
            where flagged.movement_amount / flagged.delivery_amount > ?
        ";
        $bindings = [OrgStockMovementTypeEnum::PURCHASE->value, self::CORRUPTION_WINDOW_START, self::AMOUNT_RATIO_THRESHOLD];

        if ($command->argument('organisation')) {
            $query .= ' and flagged.organisation_slug = ?';
            $bindings[] = $command->argument('organisation');
        }
        $query .= ' order by flagged.organisation_slug, flagged.id';

        $flaggedRows = DB::select($query, $bindings);

        $fixable = [];
        $skipped = [];
        foreach ($flaggedRows as $row) {
            $quantityRatio = $row->delivery_quantity / $row->movement_quantity;
            if ($quantityRatio >= self::QUANTITY_RATIO_MIN && $quantityRatio <= self::QUANTITY_RATIO_MAX) {
                $fixable[] = $row;
            } else {
                $skipped[] = $row;
            }
        }

        $this->printReport($command, $fixable, $skipped);

        if ($dryRun) {
            $command->info('Dry run: no changes written');

            return 0;
        }

        if (!$fixable) {
            $command->info('Nothing to repair');

            return 0;
        }

        $this->snapshot(array_column($fixable, 'id'), array_unique(array_column($fixable, 'org_stock_id')));

        foreach ($fixable as $row) {
            $costPerSku = round($row->delivery_amount / $row->delivery_quantity, 6);
            DB::table('org_stock_movements')->where('id', $row->id)->update([
                'cost_per_sku' => $costPerSku,
                'org_amount'   => round($costPerSku * $row->movement_quantity, 3),
                'cost_status'  => OrgStockMovementCostStatusEnum::DELIVERY->value,
            ]);
        }

        $command->info('Repaired '.count($fixable).' movements');

        return 0;
    }

    protected function printReport(Command $command, array $fixable, array $skipped): void
    {
        $command->table(
            ['movement', 'org', 'org_stock', 'date', 'mv qty', 'mv amount', 'delivery qty', 'delivery amount', 'delivery ref', 'action'],
            collect($fixable)->map(fn ($row) => $this->reportRow($row, 'fix'))
                ->concat(collect($skipped)->map(fn ($row) => $this->reportRow($row, 'SKIP qty mismatch')))
                ->all()
        );

        foreach (collect($fixable)->groupBy('organisation_slug') as $slug => $rows) {
            $command->info(sprintf(
                '%s: %d movements fixable, movement sum %s, delivery sum %s',
                $slug,
                count($rows),
                number_format($rows->sum('movement_amount'), 2),
                number_format($rows->sum(fn ($row) => $row->delivery_amount / $row->delivery_quantity * $row->movement_quantity), 2)
            ));
        }
        foreach (collect($skipped)->groupBy('organisation_slug') as $slug => $rows) {
            $command->warn(sprintf('%s: %d movements SKIPPED (quantity mismatch, suspected units problem)', $slug, count($rows)));
        }
    }

    protected function reportRow(object $row, string $action): array
    {
        return [
            $row->id,
            $row->organisation_slug,
            $row->org_stock_id,
            substr($row->date, 0, 10),
            $row->movement_quantity,
            $row->movement_amount,
            $row->delivery_quantity,
            $row->delivery_amount,
            $row->delivery_reference,
            $action,
        ];
    }

    protected function snapshot(array $movementIds, array $orgStockIds): void
    {
        DB::statement('create table if not exists org_stock_movements_pre_costfix (like org_stock_movements)');
        DB::statement('create table if not exists org_stock_histories_pre_costfix (like org_stock_histories)');

        DB::statement(
            'insert into org_stock_movements_pre_costfix
             select m.* from org_stock_movements m
             where m.id in ('.implode(',', array_map('intval', $movementIds)).')
             and not exists (select 1 from org_stock_movements_pre_costfix s where s.id = m.id)'
        );
        DB::statement(
            'insert into org_stock_histories_pre_costfix
             select h.* from org_stock_histories h
             where h.org_stock_id in ('.implode(',', array_map('intval', $orgStockIds)).')
             and not exists (select 1 from org_stock_histories_pre_costfix s where s.id = h.id)'
        );
    }
}
