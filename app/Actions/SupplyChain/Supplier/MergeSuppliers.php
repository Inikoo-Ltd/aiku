<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Supplier;

use App\Actions\Procurement\OrgSupplier\Hydrators\OrgSupplierHydrateOrgSupplierProducts;
use App\Actions\Procurement\OrgSupplier\Hydrators\OrgSupplierHydratePurchaseOrders;
use App\Actions\Procurement\OrgSupplier\Hydrators\OrgSupplierHydrateStockDeliveries;
use App\Actions\Procurement\OrgSupplierProducts\RepairOrgSupplierProductsSupplierDrift;
use App\Actions\SupplyChain\Agent\Hydrators\AgentHydrateSupplierProducts;
use App\Actions\SupplyChain\Agent\Hydrators\AgentHydrateSuppliers;
use App\Actions\SupplyChain\SupplierProduct\MergeSupplierProducts;
use App\Enums\SupplyChain\SupplierChangeRequest\SupplierChangeRequestStateEnum;
use App\Enums\SupplyChain\SupplierChangeRequest\SupplierChangeRequestTypeEnum;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierChangeRequest;
use Illuminate\Support\Arr;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Folds a duplicate supplier record into the one kept: its products, purchase orders, containers, messages,
 * invoices and attachments move over, per organisation onto the kept supplier's org supplier, and the duplicate
 * is archived, never deleted. Where both are on one agent order, the duplicate's supplier order hands its lines and
 * stage dates to the kept one and is removed, unless it carries costing, deliveries, deposits or notes. Products with the same code on both are
 * merged first through MergeSupplierProducts. Refused when the two price in different currencies or a shared product
 * cannot be merged (different pack, trade units or SKOs), since that would change what a cost means.
 */
class MergeSuppliers
{
    use AsAction;

    private const array SUPPLIER_TABLES = [
        'purchase_orders',
        'stock_deliveries',
        'agent_supplier_purchase_orders',
        'shopping_list_items',
        'supplier_declarations',
        'supplier_invoices',
        'supplier_messages',
    ];

    private const array AGENT_ORDER_STAGE_DATES = [
        'estimated_received_at',
        'proposed_ready_at',
        'approved_ready_at',
        'sample_approved_at',
        'produced_at',
        'handed_over_at',
        'qc_passed_at',
        'compliance_complete_at',
    ];

    /**
     * @param  callable(string $step, int $done, int $total): void|null  $onProgress
     */
    public function handle(Supplier $duplicate, Supplier $kept, ?callable $onProgress = null): Supplier
    {
        $report = fn (string $step, int $done = 0, int $total = 0) => $onProgress ? $onProgress($step, $done, $total) : null;

        $report('checking');
        $this->ensureCanMerge($duplicate, $kept);

        $touchedOrgSuppliers = DB::transaction(function () use ($duplicate, $kept, $report) {
            MergeSupplierProducts::make()->mergeSupplier($duplicate, $kept, fix: true, onProduct: fn (int $done, int $total) => $report('products', $done, $total));
            $this->ensureNoSharedProductCodes($duplicate, $kept);

            $report('moving');

            $productIds = $duplicate->supplierProducts()->pluck('id');
            DB::table('supplier_products')->whereIn('id', $productIds)->update(['supplier_id' => $kept->id, 'agent_id' => $kept->agent_id]);

            $touched = collect();
            foreach ($duplicate->orgSuppliers()->get() as $from) {
                $to = OrgSupplier::where('supplier_id', $kept->id)->where('organisation_id', $from->organisation_id)->first();

                if (!$to) {
                    $from->update(['supplier_id' => $kept->id]);
                    $this->renameParent($from, $kept);
                    $touched->push($from);

                    continue;
                }

                $this->moveOrgSupplierProducts($from, $to);
                DB::table('supplier_messages')->where('org_supplier_id', $from->id)->update(['org_supplier_id' => $to->id]);
                foreach (['purchase_orders', 'stock_deliveries'] as $table) {
                    DB::table($table)->where('parent_type', 'OrgSupplier')->where('parent_id', $from->id)
                        ->update(['parent_id' => $to->id, 'parent_code' => $kept->code, 'parent_name' => $kept->name]);
                }
                $from->update(['status' => false]);
                $touched->push($from, $to);
            }

            $this->foldSharedAgentOrders($duplicate, $kept);

            foreach (self::SUPPLIER_TABLES as $table) {
                DB::table($table)->where('supplier_id', $duplicate->id)->update(['supplier_id' => $kept->id]);
            }

            DB::table('supplier_stock_families')->where('supplier_id', $duplicate->id)
                ->whereNotIn('stock_family_id', DB::table('supplier_stock_families')->where('supplier_id', $kept->id)->select('stock_family_id'))
                ->update(['supplier_id' => $kept->id]);
            foreach (['suppliers', 'supplier_products'] as $table) {
                DB::table($table)->where('packaging_supplier_id', $duplicate->id)->update(['packaging_supplier_id' => $kept->id]);
            }
            DB::table('model_has_attachments')->where('model_type', 'Supplier')->where('model_id', $duplicate->id)->update(['model_id' => $kept->id]);
            foreach (['supplier_agreed_terms', 'procurement_notes'] as $table) {
                DB::table($table)->where('subject_type', 'Supplier')->where('subject_id', $duplicate->id)->update(['subject_id' => $kept->id]);
            }

            $duplicate->update([
                'status'      => false,
                'archived_at' => now(),
                'data'        => [...($duplicate->data ?? []), 'merged_into_supplier_id' => $kept->id],
            ]);

            return $touched;
        });

        $report('refreshing');
        $this->hydrate($duplicate, $kept, $touchedOrgSuppliers);

        return $kept->refresh();
    }

    /**
     * A product the kept org supplier already buys is folded into its row, as old data can leave the duplicate's org
     * supplier holding the kept supplier's products. A folded row with order history stays discontinued on the
     * archived org supplier; every other row moves over.
     */
    private function moveOrgSupplierProducts(OrgSupplier $from, OrgSupplier $to): void
    {
        $twins = OrgSupplierProduct::where('org_supplier_id', $to->id)->get()->keyBy('supplier_product_id');

        foreach (OrgSupplierProduct::where('org_supplier_id', $from->id)->whereIn('supplier_product_id', $twins->keys())->get() as $orgSupplierProduct) {
            RepairOrgSupplierProductsSupplierDrift::make()->mergeIntoTwin($orgSupplierProduct, $twins->get($orgSupplierProduct->supplier_product_id));
        }

        DB::table('org_supplier_products')
            ->where('org_supplier_id', $from->id)
            ->whereNotIn('supplier_product_id', $twins->keys())
            ->update(['org_supplier_id' => $to->id]);
    }

    private function ensureCanMerge(Supplier $duplicate, Supplier $kept): void
    {
        $blockers = $this->blockers($duplicate, $kept);

        if ($blockers) {
            throw ValidationException::withMessages(['target_supplier_id' => implode(' ', $blockers)]);
        }
    }

    /**
     * Who merged an archived duplicate comes from the approved merge suggestion; a merge without one shows only when.
     *
     * @return array{supplier: Supplier, code: string, name: string, merged_by: string|null, merged_at: mixed}|null
     */
    public static function mergedInto(Supplier $duplicate): ?array
    {
        $keptSupplierId = Arr::get($duplicate->data ?? [], 'merged_into_supplier_id');
        $kept           = $keptSupplierId ? Supplier::find($keptSupplierId) : null;

        if (!$kept) {
            return null;
        }

        $approvedMerge = SupplierChangeRequest::where('supplier_id', $duplicate->id)
            ->where('target_supplier_id', $kept->id)
            ->where('type', SupplierChangeRequestTypeEnum::MERGE)
            ->where('state', SupplierChangeRequestStateEnum::APPROVED)
            ->with('decidedBy')
            ->latest('decided_at')
            ->first();

        return [
            'supplier'  => $kept,
            'code'      => $kept->code,
            'name'      => $kept->name,
            'merged_by' => $approvedMerge?->decidedBy?->contact_name ?: $approvedMerge?->decidedBy?->username,
            'merged_at' => $approvedMerge?->decided_at ?? $duplicate->archived_at,
        ];
    }

    /**
     * @return array<int, string> why the merge cannot go ahead, empty when it can
     */
    public function blockers(Supplier $duplicate, Supplier $kept): array
    {
        if ($duplicate->id === $kept->id) {
            return [__('A supplier cannot be merged into itself')];
        }

        $blockers = [];

        if ($duplicate->currency_id !== $kept->currency_id) {
            $blockers[] = __('The two suppliers price in different currencies');
        }

        $refusedProducts = MergeSupplierProducts::make()->mergeSupplier($duplicate, $kept)['refused'];
        if ($refusedProducts) {
            $blockers[] = __('Both suppliers have products with the codes :codes that cannot be merged automatically, merge those products first', [
                'codes' => collect($refusedProducts)->map(fn (string $reason, int|string $code) => "$code ($reason)")->implode(', '),
            ]);
        }

        $deletedSharedCodes = $this->deletedSharedProductCodes($duplicate, $kept);
        if ($deletedSharedCodes->isNotEmpty()) {
            $blockers[] = __('Both suppliers have products with the codes :codes, deleted on at least one of them, rename or restore those products first', [
                'codes' => $deletedSharedCodes->implode(', '),
            ]);
        }

        $blockedAgentOrders = $this->blockedAgentOrderReferences($duplicate, $kept);
        if ($blockedAgentOrders->isNotEmpty()) {
            $blockers[] = __('Both suppliers are on the agent orders :references, sort those orders out first', ['references' => $blockedAgentOrders->implode(', ')]);
        }

        return $blockers;
    }

    /**
     * @return Collection<int, string>
     */
    public function blockedAgentOrderReferences(Supplier $duplicate, Supplier $kept): Collection
    {
        return $this->sharedAgentOrders($duplicate, $kept)
            ->join('purchase_orders', 'purchase_orders.id', '=', 'duplicate_orders.purchase_order_id')
            ->where(function ($query) {
                $query->where('duplicate_orders.is_costed', true)
                    ->orWhere('duplicate_orders.number_stock_deliveries', '>', 0)
                    ->orWhere('duplicate_orders.deposit_amount', '>', 0)
                    ->orWhereNotNull('duplicate_orders.deposit_paid_at')
                    ->orWhereNotNull('duplicate_orders.balance_paid_at')
                    ->orWhere('duplicate_orders.notes', '<>', '');
            })
            ->pluck('purchase_orders.reference');
    }

    /**
     * @return Collection<int, string>
     */
    public function sharedAgentOrderReferences(Supplier $duplicate, Supplier $kept): Collection
    {
        return $this->sharedAgentOrders($duplicate, $kept)
            ->join('purchase_orders', 'purchase_orders.id', '=', 'duplicate_orders.purchase_order_id')
            ->pluck('purchase_orders.reference');
    }

    /**
     * Codes on both suppliers where one of the two products is deleted: the product merge only folds live
     * products, so these would stop the merge inside its transaction.
     *
     * @return Collection<int, string>
     */
    public function deletedSharedProductCodes(Supplier $duplicate, Supplier $kept): Collection
    {
        $keptProducts = $kept->supplierProducts()->withTrashed()->get(['id', 'code', 'deleted_at'])->groupBy('code');

        return $duplicate->supplierProducts()->withTrashed()
            ->whereIn('code', $keptProducts->keys())
            ->get(['id', 'code', 'deleted_at'])
            ->filter(fn ($product) => $product->trashed() || $keptProducts->get($product->code)->contains(fn ($keptProduct) => $keptProduct->trashed()))
            ->pluck('code')
            ->unique()
            ->values();
    }

    private function ensureNoSharedProductCodes(Supplier $duplicate, Supplier $kept): void
    {
        $sharedCodes = $duplicate->supplierProducts()->withTrashed()
            ->whereIn('code', $kept->supplierProducts()->withTrashed()->select('code'))
            ->pluck('code');

        if ($sharedCodes->isNotEmpty()) {
            throw ValidationException::withMessages([
                'target_supplier_id' => __('Both suppliers have products with the codes :codes, merge those products first', ['codes' => $sharedCodes->implode(', ')]),
            ]);
        }
    }

    private function sharedAgentOrders(Supplier $duplicate, Supplier $kept): Builder
    {
        return DB::table('agent_supplier_purchase_orders as duplicate_orders')
            ->join('agent_supplier_purchase_orders as kept_orders', function ($join) use ($kept) {
                $join->on('kept_orders.purchase_order_id', '=', 'duplicate_orders.purchase_order_id')
                    ->where('kept_orders.supplier_id', $kept->id)
                    ->whereNull('kept_orders.deleted_at');
            })
            ->where('duplicate_orders.supplier_id', $duplicate->id)
            ->whereNull('duplicate_orders.deleted_at');
    }

    private function foldSharedAgentOrders(Supplier $duplicate, Supplier $kept): void
    {
        $pairs = $this->sharedAgentOrders($duplicate, $kept)->select('duplicate_orders.id as duplicate_id', 'kept_orders.id as kept_id')->get();

        foreach ($pairs as $pair) {
            DB::table('purchase_order_transactions')->where('agent_supplier_purchase_order_id', $pair->duplicate_id)
                ->update(['agent_supplier_purchase_order_id' => $pair->kept_id]);

            $duplicateDates = DB::table('agent_supplier_purchase_orders')->where('id', $pair->duplicate_id)->first(self::AGENT_ORDER_STAGE_DATES);
            foreach (self::AGENT_ORDER_STAGE_DATES as $column) {
                DB::table('agent_supplier_purchase_orders')->where('id', $pair->kept_id)->whereNull($column)->update([$column => $duplicateDates->$column]);
            }

            DB::table('agent_supplier_purchase_orders')->where('id', $pair->duplicate_id)->update(['deleted_at' => now()]);
        }
    }

    private function renameParent(OrgSupplier $orgSupplier, Supplier $kept): void
    {
        foreach (['purchase_orders', 'stock_deliveries'] as $table) {
            DB::table($table)->where('parent_type', 'OrgSupplier')->where('parent_id', $orgSupplier->id)
                ->update(['parent_code' => $kept->code, 'parent_name' => $kept->name]);
        }
    }

    private function hydrate(Supplier $duplicate, Supplier $kept, $touchedOrgSuppliers): void
    {
        foreach ([$duplicate, $kept] as $supplier) {
            HydrateSuppliers::run($supplier);
        }

        foreach ($touchedOrgSuppliers->unique('id') as $orgSupplier) {
            OrgSupplierHydrateOrgSupplierProducts::run($orgSupplier);
            OrgSupplierHydratePurchaseOrders::run($orgSupplier);
            OrgSupplierHydrateStockDeliveries::run($orgSupplier);
        }

        if ($kept->agent) {
            AgentHydrateSuppliers::dispatch($kept->agent);
            AgentHydrateSupplierProducts::dispatch($kept->agent);
        }

        PurchaseOrder::where('supplier_id', $kept->id)->searchable();
    }
}
