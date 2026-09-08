<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 13 Aug 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Inventory\OrgStockMovement;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateSkuValue;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementCostStatusEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Inventory\OrgStock;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Laravel\Nightwatch\Facades\Nightwatch;

/**
 * Reprices purchase movements from what the delivery says was paid: the delivery item's
 * org_net_amount over its unit_quantity expressed in SKOs. net_amount is the supplier's
 * currency and unit_quantity counts individual trade units, so reading either raw priced
 * movements in the wrong currency and the wrong unit.
 */
class RepairOrgStockMovementCostFromStockDeliveryItems
{
    use AsAction;

    /**
     * A movement costing more than its delivery item is normal: the delivery holds the goods
     * price while the movement carries the landed cost, and that premium runs to about half
     * again. Repricing on disagreement alone would strip shipping and duties off thousands of
     * correct rows, so a movement is only repriced on evidence of a specific defect: its cost
     * lands on the delivery's supplier-currency figure, which is the exchange conversion never
     * being applied, or it stands so far above the goods price that no landed cost explains it.
     */
    public const float COST_DISAGREEMENT_TOLERANCE = 0.02;
    public const float GROSS_OVERSTATEMENT_MULTIPLE = 3;

    /**
     * Deliveries arrive over several movements, so the delivery's SKOs are compared against all
     * of them together; comparing one movement against the whole delivery skipped every partial
     * receipt. What is still being put away is compared on the quantity placed so far, since the
     * rest has not been posted yet, while the cost still comes from the whole line. A total that
     * does not match even then means the two sides count different things, which in practice is
     * a stale packed_in on the org stock, and the delivery cannot price it.
     */
    public const float QUANTITY_RATIO_MIN = 0.5;
    public const float QUANTITY_RATIO_MAX = 2;

    /** @var array<int, float> */
    private array $groupExchanges = [];

    /**
     * The org_amount corruption came from refetches recomputing quantity * value_in_locations,
     * and only recently fetched movements hit that path. This is not the repair's own boundary:
     * RestorePreCorruptionOrgStockMovements rolls back by it, so it stays put.
     */
    public const string CORRUPTION_WINDOW_START = '2026-06-01';

    /**
     * What a row looks like now decides whether it is repaired, not how old it is: the selection
     * carries its own evidence, so a run reaches every movement unless --from narrows it.
     */
    public const string REPAIR_FROM_DEFAULT = '1970-01-01';

    public function getCommandSignature(): string
    {
        return 'org_stock_movement:repair_cost_from_stock_delivery_items {organisation?} {--from= : Only repair movements from this date onwards} {--dry-run : Report what would change without writing}';
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();
        $dryRun = $command->option('dry-run');

        $query = "
            select * from (
                select *,
                    sum(movement_quantity) over (partition by org_stock_id, delivery_source) as delivery_movement_quantity,
                    movement_amount / movement_quantity as movement_cost,
                    delivery_amount / nullif(delivery_quantity, 0) as delivery_cost,
                    delivery_supplier_amount / nullif(delivery_quantity, 0) as delivery_supplier_cost
                from (
                select distinct on (m.id)
                    m.id,
                    m.organisation_id,
                    m.org_stock_id,
                    m.date,
                    m.quantity      as movement_quantity,
                    m.org_amount    as movement_amount,
                    m.cost_per_sku  as movement_cost_per_sku,
                    sdi.org_net_amount as delivery_amount,
                    sdi.net_amount     as delivery_supplier_amount,
                    sdi.unit_quantity / coalesce(nullif(os.packed_in, 0), 1) as delivery_quantity,
                    nullif(sdi.unit_quantity_placed, 0) / coalesce(nullif(os.packed_in, 0), 1) as delivery_placed_quantity,
                    sd.reference      as delivery_reference,
                    sd.source_id      as delivery_source,
                    o.slug            as organisation_slug
                from org_stock_movements m
                join organisations o on o.id = m.organisation_id
                join org_stocks os on os.id = m.org_stock_id
                join stock_deliveries sd
                    on sd.source_id = m.organisation_id::text || ':' || substring(m.note from 'delivery/([0-9]+)')
                join stock_delivery_items sdi
                    on sdi.stock_delivery_id = sd.id and sdi.org_stock_id = m.org_stock_id
                where m.type = ?
                    and m.date >= ?
                    and m.note ~ 'delivery/[0-9]+'
                    and m.quantity > 0
                    and sdi.org_net_amount > 0
                    and sdi.unit_quantity > 0
                order by m.id, sdi.id
            ) flagged
        ) scoped
        where abs(scoped.movement_cost / scoped.delivery_cost - 1) > ?
            and (
                abs(scoped.movement_cost / nullif(scoped.delivery_supplier_cost, 0) - 1) <= ?
                or scoped.movement_cost / scoped.delivery_cost > ?
            )
        ";
        $from = $command->option('from') ?: self::REPAIR_FROM_DEFAULT;
        $bindings = [
            OrgStockMovementTypeEnum::PURCHASE->value,
            $from,
            self::COST_DISAGREEMENT_TOLERANCE,
            self::COST_DISAGREEMENT_TOLERANCE,
            self::GROSS_OVERSTATEMENT_MULTIPLE,
        ];

        if ($command->argument('organisation')) {
            $query .= ' and scoped.organisation_slug = ?';
            $bindings[] = $command->argument('organisation');
        }
        $query .= ' order by scoped.organisation_slug, scoped.id';

        $command->info('Repairing movements from '.$from);

        $flaggedRows = DB::select($query, $bindings);

        $fixable = [];
        $skipped = [];
        foreach ($flaggedRows as $row) {
            $quantityRatio = ($row->delivery_placed_quantity ?? $row->delivery_quantity) / $row->delivery_movement_quantity;
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
            $orgAmount  = round($costPerSku * $row->movement_quantity, 3);
            DB::table('org_stock_movements')->where('id', $row->id)->update([
                'cost_per_sku' => $costPerSku,
                'org_amount'   => $orgAmount,
                'grp_amount'   => round($orgAmount * $this->groupExchange($row->organisation_id), 3),
                'cost_status'  => OrgStockMovementCostStatusEnum::DELIVERY->value,
            ]);
        }

        $orgStockIds = array_unique(array_column($fixable, 'org_stock_id'));
        foreach ($orgStockIds as $orgStockId) {
            $orgStock = OrgStock::find($orgStockId);
            if ($orgStock) {
                OrgStockHydrateSkuValue::dispatch($orgStock);
            }
        }

        $command->info('Repaired '.count($fixable).' movements, re-hydrating '.count($orgStockIds).' org stocks');

        return 0;
    }

    /**
     * A movement's grp_amount is its org_amount in group currency, and every other writer keeps
     * the pair in step; leaving it behind would show the old, corrupt figure beside the repaired one.
     */
    protected function groupExchange(int $organisationId): float
    {
        return $this->groupExchanges[$organisationId] ??= (function () use ($organisationId) {
            $organisation = Organisation::find($organisationId);

            return $organisation ? GetCurrencyExchange::run($organisation->currency, $organisation->group->currency) : 1.0;
        })();
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
