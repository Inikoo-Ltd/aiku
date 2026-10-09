<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026 18:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Procurement;

use App\Actions\Procurement\OrgAgent\Hydrators\OrgAgentHydratePurchaseOrders;
use App\Actions\Procurement\OrgSupplier\Hydrators\OrgSupplierHydratePurchaseOrders;
use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateTransactions;
use App\Actions\SupplyChain\Agent\Hydrators\AgentHydratePurchaseOrders;
use App\Actions\SupplyChain\Supplier\Hydrators\SupplierHydratePurchaseOrders;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePurchaseOrders;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePurchaseOrders;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\AgentSupplierPurchaseOrder;
use App\Models\SupplyChain\Supplier;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * INI-059: an order to an agent becomes one purchase order per supplier behind the agent, the order
 * the supplier really ships against. Each org-agent purchase order is split by the supplier of its
 * lines (its agent supplier purchase order, else the line's supplier product, else the only supplier
 * of that SKO under the same agent). The new order keeps the original order's state, dates,
 * currency and exchanges, takes the agent supplier purchase order's reference, dates and handover
 * fields, its lines, a copy of its history and the stock deliveries holding its lines, and keeps
 * the original reference as its agent order reference, so the splits still open as one agent order. The original
 * order is soft deleted and keeps the ids of its splits in data->split_into. Lines no supplier can be
 * found for stay on the original order, which is then kept.
 */
class SplitAgentPurchaseOrders
{
    use AsAction;

    public string $commandSignature = 'procurement:split_agent_purchase_orders {--dry-run} {--organisation= : organisation slug} {--limit=}';

    private const array ASPO_FIELDS = [
        'estimated_delivery_days',
        'estimated_received_at',
        'deposit_amount',
        'deposit_paid_at',
        'balance_paid_at',
        'proposed_ready_at',
        'approved_ready_at',
        'handed_over_at',
        'qc_passed_at',
        'compliance_complete_at',
        'sample_approved_at',
        'produced_at',
    ];

    /**
     * @var array<string, int>
     */
    private array $report = [
        'purchase_orders'           => 0,
        'split_into'                => 0,
        'lines_moved'               => 0,
        'lines_by_org_stock'        => 0,
        'lines_left_unattributed'   => 0,
        'purchase_orders_kept'      => 0,
        'with_extra_costs'          => 0,
        'stock_delivery_links'      => 0,
        'audits_copied'             => 0,
        'deposits_moved'            => 0,
        'in_process_beside_another' => 0,
        'empty_drafts_deleted'      => 0,
        'failed'                    => 0,
    ];

    /**
     * @var array<int, string>
     */
    public array $failures = [];

    /**
     * @return array<string, int>
     */
    public function handle(bool $dryRun = false, ?string $organisationSlug = null, ?int $limit = null): array
    {
        $query = PurchaseOrder::query()
            ->where('parent_type', 'OrgAgent')
            ->when($organisationSlug, fn ($query) => $query->whereHas('organisation', fn ($query) => $query->where('slug', $organisationSlug)))
            ->orderBy('id')
            ->when($limit, fn ($query) => $query->limit($limit));

        $touchedOrgSuppliers = [];
        $touchedOrgAgents    = [];
        try {
            $this->splitAll($query->get(), $dryRun, $touchedOrgSuppliers, $touchedOrgAgents);
        } finally {
            if (!$dryRun) {
                $this->hydrate(array_keys($touchedOrgSuppliers), array_keys($touchedOrgAgents));
            }
        }

        return $this->report;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, PurchaseOrder>  $purchaseOrders
     * @param  array<int, bool>  $touchedOrgSuppliers
     * @param  array<int, bool>  $touchedOrgAgents
     */
    private function splitAll($purchaseOrders, bool $dryRun, array &$touchedOrgSuppliers, array &$touchedOrgAgents): void
    {
        foreach ($purchaseOrders as $purchaseOrder) {
            $groups = $this->groupLinesBySupplier($purchaseOrder);
            if ($groups->isEmpty()) {
                if ($purchaseOrder->state->value === 'in_process') {
                    $this->report['empty_drafts_deleted']++;
                    if (!$dryRun) {
                        $purchaseOrder->delete();
                    }
                }

                continue;
            }

            $this->report['purchase_orders']++;

            if ($dryRun) {
                $this->countDryRun($purchaseOrder, $groups);

                continue;
            }

            PurchaseOrder::disableAuditing();
            try {
                $splits = DB::transaction(fn () => $this->split($purchaseOrder, $groups));
            } catch (Throwable $e) {
                $this->report['failed']++;
                $this->failures[] = $purchaseOrder->reference.': '.$e->getMessage();

                continue;
            } finally {
                PurchaseOrder::enableAuditing();
            }

            $touchedOrgAgents[$purchaseOrder->parent_id] = true;
            foreach ($splits as $split) {
                $touchedOrgSuppliers[$split->parent_id] = true;
            }
        }
    }

    /**
     * @return Collection<int|string, Collection<int, object>> lines keyed by supplier id, unattributed lines under 'none'
     */
    private function groupLinesBySupplier(PurchaseOrder $purchaseOrder): Collection
    {
        $lines = DB::table('purchase_order_transactions')
            ->leftJoin('agent_supplier_purchase_orders', function ($join) use ($purchaseOrder) {
                $join->on('agent_supplier_purchase_orders.id', 'purchase_order_transactions.agent_supplier_purchase_order_id')
                    ->where('agent_supplier_purchase_orders.purchase_order_id', $purchaseOrder->id);
            })
            ->leftJoin('supplier_products', 'supplier_products.id', 'purchase_order_transactions.supplier_product_id')
            ->where('purchase_order_transactions.purchase_order_id', $purchaseOrder->id)
            ->whereNull('purchase_order_transactions.deleted_at')
            ->get([
                'purchase_order_transactions.id',
                'purchase_order_transactions.org_stock_id',
                DB::raw('coalesce(agent_supplier_purchase_orders.supplier_id, supplier_products.supplier_id) as supplier_id'),
            ]);

        return $lines->map(function (object $line) use ($purchaseOrder) {
            if (!$line->supplier_id && $line->org_stock_id) {
                $line->supplier_id     = $this->onlySupplierOfOrgStockUnderAgent($line->org_stock_id, $purchaseOrder->parent_id);
                $line->by_org_stock    = (bool) $line->supplier_id;
            }

            return $line;
        })->groupBy(fn (object $line) => $line->supplier_id ?: 'none');
    }

    private function onlySupplierOfOrgStockUnderAgent(int $orgStockId, int $orgAgentId): ?int
    {
        $supplierIds = DB::table('org_stock_has_org_supplier_products')
            ->join('org_supplier_products', 'org_supplier_products.id', 'org_stock_has_org_supplier_products.org_supplier_product_id')
            ->join('supplier_products', 'supplier_products.id', 'org_supplier_products.supplier_product_id')
            ->where('org_stock_has_org_supplier_products.org_stock_id', $orgStockId)
            ->where('org_supplier_products.org_agent_id', $orgAgentId)
            ->distinct()
            ->pluck('supplier_products.supplier_id');

        return $supplierIds->count() === 1 ? $supplierIds->first() : null;
    }

    private function countDryRun(PurchaseOrder $purchaseOrder, Collection $groups): void
    {
        foreach ($groups as $supplierId => $lines) {
            $orgSupplier = $supplierId === 'none' ? null : $this->orgSupplier($purchaseOrder, $supplierId);
            if (!$orgSupplier) {
                $this->report['lines_left_unattributed'] += $lines->count();

                continue;
            }
            $this->report['split_into']++;
            $this->report['lines_moved']        += $lines->count();
            $this->report['lines_by_org_stock'] += $lines->where('by_org_stock', true)->count();
        }

        if ($groups->has('none') || $groups->keys()->contains(fn ($supplierId) => $supplierId !== 'none' && !$this->orgSupplier($purchaseOrder, $supplierId))) {
            $this->report['purchase_orders_kept']++;
        }
        if ($this->hasExtraCosts($purchaseOrder)) {
            $this->report['with_extra_costs']++;
        }
    }

    /**
     * @return array<int, PurchaseOrder>
     */
    private function split(PurchaseOrder $purchaseOrder, Collection $groups): array
    {
        $aspos = AgentSupplierPurchaseOrder::where('purchase_order_id', $purchaseOrder->id)->get()->keyBy('supplier_id');

        $splits     = [];
        $leftOnOrder = 0;
        foreach ($groups as $supplierId => $lines) {
            $orgSupplier = $supplierId === 'none' ? null : $this->orgSupplier($purchaseOrder, $supplierId);
            if (!$orgSupplier) {
                $leftOnOrder += $lines->count();

                continue;
            }

            $split = $this->createSplit($purchaseOrder, $orgSupplier, $aspos->get($supplierId));

            DB::table('purchase_order_transactions')->whereIn('id', $lines->pluck('id'))->update(['purchase_order_id' => $split->id]);

            $itemsNet = (float) DB::table('purchase_order_transactions')->whereIn('id', $lines->pluck('id'))->whereNull('deleted_at')->sum('net_amount');
            $split->updateQuietly(['cost_items' => $itemsNet, 'cost_total' => $itemsNet]);
            PurchaseOrderHydrateTransactions::run($split);

            $this->report['split_into']++;
            $this->report['lines_moved']        += $lines->count();
            $this->report['lines_by_org_stock'] += $lines->where('by_org_stock', true)->count();

            $splits[] = $split;
        }

        if (!$splits) {
            return [];
        }

        $preSplit = $purchaseOrder->only(['cost_items', 'cost_extra', 'cost_shipping', 'cost_duties', 'cost_tax', 'cost_total']);
        $purchaseOrder->data = array_merge(
            $purchaseOrder->data ?? [],
            ['split_into' => array_merge(Arr::get($purchaseOrder->data, 'split_into', []), Arr::pluck($splits, 'id'))],
            Arr::has($purchaseOrder->data, 'pre_split') ? [] : ['pre_split' => $preSplit]
        );

        if ($leftOnOrder) {
            $remainingItems = (float) $purchaseOrder->purchaseOrderTransactions()->sum('net_amount');
            $purchaseOrder->cost_items = $remainingItems;
            $purchaseOrder->cost_total = $remainingItems;
        }

        if ($this->hasExtraCosts($purchaseOrder)) {
            $this->report['with_extra_costs']++;
            $this->spreadExtraCosts($preSplit, $leftOnOrder ? [...$splits, $purchaseOrder] : $splits);
        }

        $purchaseOrder->saveQuietly();
        if ($leftOnOrder) {
            PurchaseOrderHydrateTransactions::run($purchaseOrder);
        }

        if ($leftOnOrder) {
            $this->report['lines_left_unattributed'] += $leftOnOrder;
            $this->report['purchase_orders_kept']++;
        } else {
            $purchaseOrder->delete();
        }

        $this->relinkStockDeliveries($purchaseOrder, $splits);
        $this->copyAudits($purchaseOrder, $splits);

        return $splits;
    }

    private function orgSupplier(PurchaseOrder $purchaseOrder, int $supplierId): ?OrgSupplier
    {
        return OrgSupplier::where('organisation_id', $purchaseOrder->organisation_id)->where('supplier_id', $supplierId)->first();
    }

    private function createSplit(PurchaseOrder $purchaseOrder, OrgSupplier $orgSupplier, ?AgentSupplierPurchaseOrder $aspo): PurchaseOrder
    {
        /** @var Supplier $supplier */
        $supplier = Supplier::withTrashed()->find($orgSupplier->supplier_id);

        if ($purchaseOrder->state->value === 'in_process' && $orgSupplier->purchaseOrders()->where('state', 'in_process')->exists()) {
            $this->report['in_process_beside_another']++;
        }

        $split = $purchaseOrder->replicate(['slug', 'source_id', 'fetched_at', 'last_fetched_at', 'deleted_at', 'agent_supplier_purchase_order_id']);
        $split->fill([
            'parent_type' => 'OrgSupplier',
            'parent_id'   => $orgSupplier->id,
            'parent_code' => $supplier->code,
            'parent_name' => $supplier->name,
            'supplier_id' => $supplier->id,
            'agent_id'    => $purchaseOrder->agent_id ?? OrgAgent::find($purchaseOrder->parent_id)?->agent_id,
            'reference'   => $this->uniqueReference($purchaseOrder, $aspo?->reference ?: $purchaseOrder->reference.'-'.$supplier->code),
            'agent_order_reference' => $purchaseOrder->reference,
            'cost_extra'    => 0,
            'cost_shipping' => 0,
            'cost_duties'   => 0,
            'cost_tax'      => 0,
            'data'        => array_merge(
                Arr::except($purchaseOrder->data ?? [], 'split_into'),
                ['split_from' => ['id' => $purchaseOrder->id, 'reference' => $purchaseOrder->reference]],
                array_filter(['housekeeping' => Arr::get($aspo?->data, 'housekeeping')])
            ),
        ]);

        if ($aspo) {
            foreach (self::ASPO_FIELDS as $field) {
                if ($aspo->{$field} !== null) {
                    $split->{$field} = $aspo->{$field};
                }
            }
            $split->chs_excluded                     = (bool) $aspo->chs_excluded;
            $split->chs_exclusion_reason             = $aspo->chs_exclusion_reason;
            $split->agent_supplier_purchase_order_id = $aspo->id;
            if ($aspo->notes) {
                $split->notes = trim($purchaseOrder->notes."\n".$aspo->notes);
            }
        }

        $split->save();

        if ($aspo) {
            $this->report['deposits_moved'] += DB::table('aspo_deposits')->where('agent_supplier_purchase_order_id', $aspo->id)->update(['purchase_order_id' => $split->id]);
        }

        return $split;
    }

    private function uniqueReference(PurchaseOrder $purchaseOrder, string $reference): string
    {
        $candidate = $reference;
        $suffix    = 2;
        while (PurchaseOrder::withTrashed()->where('organisation_id', $purchaseOrder->organisation_id)->where('reference', $candidate)->exists()) {
            $candidate = $reference.'-'.$suffix++;
        }

        return $candidate;
    }

    /**
     * The order's extra costs (mostly the agent's charge) go to each order by its share of the items,
     * rounded to cents with the last order taking the remainder, so they add up to the original. A
     * kept original takes its share too.
     *
     * @param  array<string, mixed>  $preSplit
     * @param  array<int, PurchaseOrder>  $orders
     */
    private function spreadExtraCosts(array $preSplit, array $orders): void
    {
        $itemsTotal = array_sum(array_map(fn (PurchaseOrder $order) => (float) $order->cost_items, $orders));
        $fields     = ['cost_extra', 'cost_shipping', 'cost_duties', 'cost_tax'];
        $allocated  = array_fill_keys($fields, 0.0);
        $last       = count($orders) - 1;

        foreach (array_values($orders) as $index => $order) {
            $share   = $itemsTotal > 0 ? (float) $order->cost_items / $itemsTotal : ($index === 0 ? 1 : 0);
            $amounts = [];
            foreach ($fields as $field) {
                $total             = (float) ($preSplit[$field] ?? 0);
                $amounts[$field]   = $index === $last ? round($total - $allocated[$field], 2) : round($total * $share, 2);
                $allocated[$field] += $amounts[$field];
            }

            $order->fill(array_merge($amounts, ['cost_total' => round((float) $order->cost_items + array_sum($amounts), 2)]));
            if ($order->exists && $order->isDirty()) {
                $order->saveQuietly();
            }
        }
    }

    private function hasExtraCosts(PurchaseOrder $purchaseOrder): bool
    {
        return (float) $purchaseOrder->cost_extra + (float) $purchaseOrder->cost_shipping + (float) $purchaseOrder->cost_duties + (float) $purchaseOrder->cost_tax != 0;
    }

    /**
     * A delivery follows the splits whose lines it holds; one with no line matched follows all of them.
     *
     * @param array<int, PurchaseOrder> $splits
     */
    private function relinkStockDeliveries(PurchaseOrder $purchaseOrder, array $splits): void
    {
        if (!$splits) {
            return;
        }

        $splitIds = Arr::pluck($splits, 'id');
        $stockDeliveryIds = DB::table('purchase_order_stock_delivery')->where('purchase_order_id', $purchaseOrder->id)->pluck('stock_delivery_id');

        foreach ($stockDeliveryIds as $stockDeliveryId) {
            $matched = DB::table('purchase_order_transactions')
                ->whereIn('purchase_order_transactions.purchase_order_id', $splitIds)
                ->whereExists(function ($items) use ($stockDeliveryId) {
                    $items->from('stock_delivery_items')
                        ->where('stock_delivery_items.stock_delivery_id', $stockDeliveryId)
                        ->where(function ($match) {
                            $match->whereRaw("(stock_delivery_items.data->>'purchase_order_transaction_id')::bigint = purchase_order_transactions.id")
                                ->orWhereColumn('stock_delivery_items.source_id', 'purchase_order_transactions.source_id')
                                ->orWhere(function ($legacy) {
                                    $legacy->whereRaw("stock_delivery_items.data->>'purchase_order_transaction_id' is null")
                                        ->whereColumn('stock_delivery_items.org_stock_id', 'purchase_order_transactions.org_stock_id');
                                });
                        });
                })
                ->distinct()
                ->pluck('purchase_order_transactions.purchase_order_id');

            $linkTo = $matched->isEmpty() ? $splitIds : $matched->all();

            DB::table('purchase_order_stock_delivery')->insertOrIgnore(
                array_map(fn ($splitId) => ['purchase_order_id' => $splitId, 'stock_delivery_id' => $stockDeliveryId], $linkTo)
            );
            $this->report['stock_delivery_links'] += count($linkTo);

            StockDelivery::whereKey($stockDeliveryId)->update([
                'number_purchase_orders' => DB::raw('(select count(*) from purchase_order_stock_delivery join purchase_orders on purchase_orders.id = purchase_order_stock_delivery.purchase_order_id where purchase_orders.deleted_at is null and purchase_order_stock_delivery.stock_delivery_id = stock_deliveries.id)'),
            ]);
        }

        foreach ($splits as $split) {
            $split->updateQuietly(['number_stock_deliveries' => $split->stockDeliveries()->count()]);
        }
    }

    /**
     * @param array<int, PurchaseOrder> $splits
     */
    private function copyAudits(PurchaseOrder $purchaseOrder, array $splits): void
    {
        $audits = DB::table('audits')->where('auditable_type', 'PurchaseOrder')->where('auditable_id', $purchaseOrder->id)->orderBy('id')->get();

        foreach ($splits as $split) {
            $rows = $audits->map(fn (object $audit) => Arr::except(array_merge((array) $audit, ['auditable_id' => $split->id, 'source_id' => null]), ['id']))->all();
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('audits')->insert($chunk);
            }
            $this->report['audits_copied'] += count($rows);
        }
    }

    /**
     * @param array<int, int> $orgSupplierIds
     * @param array<int, int> $orgAgentIds
     */
    private function hydrate(array $orgSupplierIds, array $orgAgentIds): void
    {
        $orgSuppliers = OrgSupplier::whereIn('id', $orgSupplierIds)->with(['supplier', 'orgAgent.agent', 'organisation.group'])->get();

        foreach ($orgSuppliers as $orgSupplier) {
            OrgSupplierHydratePurchaseOrders::run($orgSupplier);
            if ($orgSupplier->supplier) {
                SupplierHydratePurchaseOrders::run($orgSupplier->supplier);
            }
        }

        $orgAgents = $orgSuppliers->pluck('orgAgent')->filter()->merge(OrgAgent::whereIn('id', $orgAgentIds)->with('agent')->get());
        foreach ($orgAgents->unique('id') as $orgAgent) {
            OrgAgentHydratePurchaseOrders::run($orgAgent);
            AgentHydratePurchaseOrders::run($orgAgent->agent);
        }

        foreach ($orgSuppliers->pluck('organisation')->unique('id') as $organisation) {
            OrganisationHydratePurchaseOrders::run($organisation);
        }

        foreach ($orgSuppliers->pluck('organisation.group')->unique('id') as $group) {
            GroupHydratePurchaseOrders::run($group);
        }
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $dryRun = (bool) $command->option('dry-run');
        $report = $this->handle(
            dryRun: $dryRun,
            organisationSlug: $command->option('organisation'),
            limit: $command->option('limit') ? (int) $command->option('limit') : null,
        );

        $command->info($dryRun ? 'Dry run, nothing written' : 'Done');
        $command->table(['', 'count'], collect($report)->map(fn ($count, $key) => [$key, $count])->values()->all());

        return 0;
    }
}
