<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Procurement;

use App\Actions\Procurement\PurchaseOrder\CalculatePurchaseOrderTotalAmounts;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use Throwable;

/**
 * Leaves every purchase order carrying only what was ordered (INI-236, INI-237). Extra, shipping, duties and tax go
 * to 0: they came from Aurora, from agent orders split per supplier and from costed deliveries, and belong on the
 * stock delivery. Where a costed delivery wrote its own items amount over the order's, the items go back to the
 * order's own lines. The total becomes the items. What each order carried before is kept in
 * data.costs_before_extras_cleared. An order whose items would change is left alone and listed when it has deposit
 * payments, because deposits and agent money are reckoned from its items, when its lines carry no amounts or when
 * they can not be added up. An order with lines in another currency than its own only loses its extras, taken off
 * its total: its items are not this repair's to change. Deleted orders are left as they
 * are: the agent orders split per supplier are kept deleted with what Aurora had. Every order is listed in the CSV
 * before anything is written, and nothing is written without --apply.
 */
class RepairPurchaseOrderCosts
{
    use AsAction;

    public const string SNAPSHOT_KEY = 'costs_before_extras_cleared';

    private const array EXTRA_FIELDS = ['cost_extra', 'cost_shipping', 'cost_duties', 'cost_tax'];

    public string $commandSignature = 'repair:purchase_order_costs {--apply : Write the changes; without it nothing is written} {--id=* : Only these purchase order ids} {--csv= : Where to write every order before and after}';

    /**
     * @param  array<int, int>  $ids  only these purchase orders, every one when empty
     * @return Collection<int, array<string, mixed>>
     */
    public function handle(bool $apply = false, array $ids = [], ?string $csv = null): Collection
    {
        $changes = $this->candidates($ids)
            ->map(fn (object $row) => $this->change($row))
            ->filter()
            ->values();

        if ($csv) {
            $this->writeCsv($csv, $changes);
        }

        if ($apply) {
            $changes->where('skipped', false)->chunk(500)->each(fn (Collection $chunk) => DB::transaction(fn () => $chunk->each(fn (array $change) => $this->write($change))));
        }

        return $changes;
    }

    /**
     * @param  array<int, int>  $ids
     * @return Collection<int, object>
     */
    private function candidates(array $ids): Collection
    {
        $only     = $ids ? 'po.id in ('.implode(',', array_map('intval', $ids)).') and' : '';
        $extras   = collect(self::EXTRA_FIELDS)->map(fn (string $field) => "coalesce(po.$field, 0) <> 0")->implode(' or ');
        $placed   = StockDeliveryStateEnum::PLACED->value;
        $ownLines = '(select coalesce(sum(lines.net_amount), 0) from purchase_order_transactions lines where lines.purchase_order_id = po.id)';

        return collect(DB::select(
            "select po.id, po.reference, po.state, organisations.code as organisation, currencies.code as currency,
                po.cost_items, po.cost_extra, po.cost_shipping, po.cost_duties, po.cost_tax, po.cost_total,
                ($extras) as has_extras,
                has_placed_delivery.found and abs(coalesce(po.cost_items, 0) - $ownLines) > 0.005 as items_differ_from_lines,
                exists(select 1 from purchase_order_deposit_payments payments where payments.purchase_order_id = po.id and payments.deleted_at is null) as has_payments,
                exists(
                    select 1 from purchase_order_transactions lines
                    where lines.purchase_order_id = po.id and lines.currency_id <> po.currency_id
                ) as has_foreign_lines
            from purchase_orders po
            join organisations on organisations.id = po.organisation_id
            join currencies on currencies.id = po.currency_id
            cross join lateral (
                select exists(
                    select 1 from purchase_order_stock_delivery link
                    join stock_deliveries on stock_deliveries.id = link.stock_delivery_id
                    where link.purchase_order_id = po.id and stock_deliveries.state = ? and stock_deliveries.deleted_at is null
                ) as found
            ) has_placed_delivery
            where po.deleted_at is null and $only (($extras)
                or abs(coalesce(po.cost_total, 0) - coalesce(po.cost_items, 0)) > 0.005
                or (has_placed_delivery.found and abs(coalesce(po.cost_items, 0) - $ownLines) > 0.005))
            order by po.id",
            [$placed]
        ));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function change(object $row): ?array
    {
        $before = [
            'cost_items'    => $row->cost_items === null ? null : (float) $row->cost_items,
            'cost_extra'    => $row->cost_extra === null ? null : (float) $row->cost_extra,
            'cost_shipping' => $row->cost_shipping === null ? null : (float) $row->cost_shipping,
            'cost_duties'   => $row->cost_duties === null ? null : (float) $row->cost_duties,
            'cost_tax'      => (float) $row->cost_tax,
            'cost_total'    => (float) $row->cost_total,
        ];

        $extras    = collect(self::EXTRA_FIELDS)->sum(fn (string $field) => (float) $before[$field]);
        $items     = (float) $before['cost_items'];
        $total     = $items;
        $leftAlone = null;

        if ($row->has_foreign_lines) {
            if (!$row->has_extras && !$row->items_differ_from_lines) {
                return null;
            }
            $total     = round($before['cost_total'] - $extras, 2);
            $leftAlone = $row->items_differ_from_lines ? 'its items differ from its lines, which are in another currency than the order' : null;
        } elseif ($row->items_differ_from_lines && $row->state !== PurchaseOrderStateEnum::CANCELLED->value) {
            try {
                $items = $total = round(CalculatePurchaseOrderTotalAmounts::make()->itemsNet(PurchaseOrder::findOrFail($row->id)), 2);
            } catch (Throwable $exception) {
                $leftAlone = 'its items could not be added up from its lines: '.$exception->getMessage();
            }
        }
        $itemsChange = abs($items - (float) $before['cost_items']) > 0.005;

        if (!$leftAlone && !$row->has_extras && !$itemsChange && abs($before['cost_total'] - $total) <= 0.005) {
            return null;
        }

        $leftAlone ??= match (true) {
            $itemsChange && $row->has_payments => 'its items would change and it has deposit payments',
            $itemsChange && $items == 0.0      => 'its items would change and its lines have no amounts',
            default                            => null,
        };

        return [
            'id'           => $row->id,
            'organisation' => $row->organisation,
            'reference'    => $row->reference,
            'state'        => $row->state,
            'currency'     => $row->currency,
            'before'       => $before,
            'after'        => $leftAlone ? Arr::only($before, ['cost_items', 'cost_total']) : ['cost_items' => $items, 'cost_total' => $total],
            'items_change' => $itemsChange && !$leftAlone,
            'skipped'      => $leftAlone !== null,
            'left_alone'   => $leftAlone,
        ];
    }

    /**
     * Written straight to the table: the order is not edited, so no audit, no state change and no hydrator runs.
     * An order whose items moved since it was read is not written: the next run takes it as it then is.
     *
     * @param  array<string, mixed>  $change
     */
    private function write(array $change): void
    {
        $snapshot = DB::getPdo()->quote(json_encode([...$change['before'], 'at' => now()->toIso8601String()]));
        $key      = self::SNAPSHOT_KEY;
        $update   = [
            'cost_total' => $change['after']['cost_total'],
            'data'       => DB::raw("case
                when jsonb_typeof(data) = 'object' and jsonb_exists(data, '$key') then data
                else jsonb_set(case when jsonb_typeof(data) = 'object' then data else '{}'::jsonb end, '{{$key}}', $snapshot::jsonb)
            end"),
        ];

        foreach (self::EXTRA_FIELDS as $field) {
            if ((float) $change['before'][$field] != 0.0) {
                $update[$field] = 0;
            }
        }

        if ($change['items_change']) {
            $update['cost_items'] = $change['after']['cost_items'];
        }

        DB::table('purchase_orders')
            ->where('id', $change['id'])
            ->whereRaw('cost_items is not distinct from ?::numeric', [$change['before']['cost_items']])
            ->update($update);
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $apply   = (bool) $command->option('apply');
        $csv     = $command->option('csv') ?: storage_path('app/repair_purchase_order_costs_'.($apply ? 'applied' : 'dry_run').'_'.now()->format('Ymd_His').'.csv');
        $changes = $this->handle($apply, array_map('intval', $command->option('id')), $csv);
        $written = $changes->where('skipped', false);

        $command->table(
            ['Currency', 'Orders', 'Extras cleared', 'Items put back to own lines', 'Total before', 'Total after'],
            $written->groupBy('currency')->map(fn (Collection $orders, string $currency) => [
                $currency,
                $orders->count(),
                number_format($orders->sum(fn (array $change) => collect(self::EXTRA_FIELDS)->sum(fn (string $field) => (float) $change['before'][$field])), 2),
                $orders->where('items_change', true)->count(),
                number_format($orders->sum('before.cost_total'), 2),
                number_format($orders->sum('after.cost_total'), 2),
            ])->values()->all()
        );

        foreach ($changes->where('skipped', true) as $change) {
            $command->warn("Left alone, {$change['left_alone']}: {$change['organisation']} {$change['reference']} (id {$change['id']})");
        }

        $command->info(($apply ? 'Repaired ' : 'Would repair ').$written->count().' purchase orders. Every order before and after: '.$csv);
        if (!$apply) {
            $command->line('Nothing was written. Run again with --apply to write.');
        }

        return 0;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $changes
     */
    private function writeCsv(string $path, Collection $changes): void
    {
        $file = fopen($path, 'w');
        if ($file === false) {
            throw new RuntimeException("Can not write $path, nothing was repaired");
        }
        fputcsv($file, ['id', 'organisation', 'reference', 'state', 'currency', 'items_before', 'extra_before', 'shipping_before', 'duties_before', 'tax_before', 'total_before', 'items_after', 'total_after', 'left_alone'], escape: '');

        foreach ($changes as $change) {
            fputcsv($file, [
                $change['id'], $change['organisation'], $change['reference'], $change['state'], $change['currency'],
                $change['before']['cost_items'], $change['before']['cost_extra'], $change['before']['cost_shipping'], $change['before']['cost_duties'], $change['before']['cost_tax'], $change['before']['cost_total'],
                $change['after']['cost_items'], $change['after']['cost_total'],
                $change['left_alone'],
            ], escape: '');
        }

        fclose($file);
    }
}
