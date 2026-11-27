<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 01 Sep 2026, Sheffield, UK
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplierProducts;

use App\Actions\Procurement\OrgSupplier\Hydrators\OrgSupplierHydrateOrgSupplierProducts;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrgSupplierProducts;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Procurement\OrgSupplierProduct\OrgSupplierProductStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\Inventory\OrgStockHasOrgSupplierProduct;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\Procurement\ShoppingListItem;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairOrgSupplierProductsSupplierDrift
{
    use AsAction;

    public string $commandSignature = 'repair:org_supplier_products_supplier_drift {--fix : Apply the repointing and merge rows whose twin exists, otherwise dry run} {--history : Also move finished purchase order, delivery and shopping list lines to the twin when every one of them belongs to the twin, then delete the old row}';

    /**
     * @var array<int, array{id: int, organisation_id: int, code: string, twin_id: int, outcome: string, purchase_order_lines: int, delivery_items: int}>
     */
    public array $historyReport = [];

    /**
     * @return array{repointed: int, collisions: int, merged_deleted: int, merged_discontinued: int, discontinued_duplicate_copies: int, unfixable: int, history_moved: int, history_kept: int}
     */
    public function handle(bool $fix = false, bool $withHistory = false): array
    {
        $historyMoved = 0;
        $historyKept  = 0;

        $discontinuedDuplicateCopies = 0;
        $repointed          = 0;
        $collisions         = 0;
        $mergedDeleted      = 0;
        $mergedDiscontinued = 0;
        $unfixable          = 0;

        $touchedOrgSuppliers = [];
        $touchedOrganisations = [];

        foreach ($this->getDriftedQuery()->cursor() as $orgSupplierProduct) {
            $correctOrgSuppliers = OrgSupplier::where('organisation_id', $orgSupplierProduct->organisation_id)
                ->where('supplier_id', $orgSupplierProduct->supplierProduct->supplier_id)
                ->get();

            if ($correctOrgSuppliers->isEmpty() && $this->isRedundantCopy($orgSupplierProduct)) {
                $discontinuedDuplicateCopies++;

                if ($fix) {
                    $touchedOrgSuppliers[$orgSupplierProduct->org_supplier_id]  = $orgSupplierProduct->org_supplier_id;
                    $touchedOrganisations[$orgSupplierProduct->organisation_id] = $orgSupplierProduct->organisation_id;

                    $orgSupplierProduct->update(['state' => OrgSupplierProductStateEnum::DISCONTINUED]);
                }

                continue;
            }

            if ($correctOrgSuppliers->count() != 1) {
                $unfixable++;
                continue;
            }

            $correctOrgSupplier = $correctOrgSuppliers->first();

            $twin = OrgSupplierProduct::where('org_supplier_id', $correctOrgSupplier->id)
                ->where('supplier_product_id', $orgSupplierProduct->supplier_product_id)
                ->first();

            if ($twin) {
                $collisions++;

                $movesHistory = false;
                if ($withHistory) {
                    $movesHistory = $this->historyBelongsToTwin($orgSupplierProduct, $twin);
                    $movesHistory ? $historyMoved++ : $historyKept++;

                    $this->historyReport[] = [
                        'id'                   => $orgSupplierProduct->id,
                        'organisation_id'      => $orgSupplierProduct->organisation_id,
                        'code'                 => $orgSupplierProduct->supplierProduct->code,
                        'twin_id'              => $twin->id,
                        'outcome'              => $movesHistory ? ($fix ? 'moved and deleted' : 'would move and delete') : 'kept: lines of another supplier',
                        'purchase_order_lines' => PurchaseOrderTransaction::where('org_supplier_product_id', $orgSupplierProduct->id)->count(),
                        'delivery_items'       => StockDeliveryItem::where('org_supplier_product_id', $orgSupplierProduct->id)->count(),
                    ];
                }

                if ($fix) {
                    $touchedOrgSuppliers[$orgSupplierProduct->org_supplier_id]  = $orgSupplierProduct->org_supplier_id;
                    $touchedOrgSuppliers[$twin->org_supplier_id]                = $twin->org_supplier_id;
                    $touchedOrganisations[$orgSupplierProduct->organisation_id] = $orgSupplierProduct->organisation_id;

                    if ($this->mergeIntoTwin($orgSupplierProduct, $twin, $movesHistory)) {
                        $mergedDeleted++;
                    } else {
                        $mergedDiscontinued++;
                    }
                }

                continue;
            }

            if ($fix) {
                $touchedOrgSuppliers[$orgSupplierProduct->org_supplier_id]  = $orgSupplierProduct->org_supplier_id;
                $touchedOrgSuppliers[$correctOrgSupplier->id]               = $correctOrgSupplier->id;
                $touchedOrganisations[$orgSupplierProduct->organisation_id] = $orgSupplierProduct->organisation_id;

                $orgSupplierProduct->update([
                    'org_supplier_id' => $correctOrgSupplier->id,
                    'org_agent_id'    => $correctOrgSupplier->org_agent_id,
                ]);
            }

            $repointed++;
        }

        foreach (OrgSupplier::whereIn('id', $touchedOrgSuppliers)->get() as $orgSupplier) {
            OrgSupplierHydrateOrgSupplierProducts::run($orgSupplier);
        }

        foreach (Organisation::whereIn('id', $touchedOrganisations)->get() as $organisation) {
            OrganisationHydrateOrgSupplierProducts::run($organisation);
        }

        return [
            'repointed'           => $repointed,
            'collisions'          => $collisions,
            'merged_deleted'      => $mergedDeleted,
            'merged_discontinued' => $mergedDiscontinued,
            'discontinued_duplicate_copies' => $discontinuedDuplicateCopies,
            'unfixable'           => $unfixable,
            'history_moved'       => $historyMoved,
            'history_kept'        => $historyKept,
        ];
    }

    /**
     * A drifted row whose org supplier already carries the same product code, active, through its own supplier's
     * live copy of the product, and that nothing open or stocked points at: the organisation keeps the product
     * through that copy, so this one can be discontinued without moving anything.
     */
    public function isRedundantCopy(OrgSupplierProduct $orgSupplierProduct): bool
    {
        if ($orgSupplierProduct->state !== OrgSupplierProductStateEnum::ACTIVE->value) {
            return false;
        }

        $hasLiveSameCodeCopy = OrgSupplierProduct::where('org_supplier_id', $orgSupplierProduct->org_supplier_id)
            ->where('id', '!=', $orgSupplierProduct->id)
            ->where('state', OrgSupplierProductStateEnum::ACTIVE)
            ->whereHas('supplierProduct', fn ($query) => $query
                ->where('supplier_id', $orgSupplierProduct->orgSupplier->supplier_id)
                ->where('code', $orgSupplierProduct->supplierProduct->code)
                ->where('state', SupplierProductStateEnum::ACTIVE)
                ->where('is_available', true))
            ->exists();

        if (!$hasLiveSameCodeCopy) {
            return false;
        }

        return !OrgStockHasOrgSupplierProduct::where('org_supplier_product_id', $orgSupplierProduct->id)->exists()
            && !PurchaseOrderTransaction::where('org_supplier_product_id', $orgSupplierProduct->id)
                ->whereHas('purchaseOrder', fn ($query) => $query->whereIn('state', PurchaseOrderStateEnum::live()))
                ->exists()
            && !StockDeliveryItem::where('org_supplier_product_id', $orgSupplierProduct->id)
                ->whereHas('stockDelivery', fn ($query) => $query->whereNotIn('state', [
                    StockDeliveryStateEnum::PLACED,
                    StockDeliveryStateEnum::CANCELLED,
                    StockDeliveryStateEnum::NOT_RECEIVED,
                ]))
                ->exists();
    }

    /**
     * Every purchase order line of the drifted row is on an order to the twin's org supplier, or to the twin's
     * agent through an agent supplier order of the product's own supplier, and every delivery item came from
     * the twin's org supplier or agent: the history was the twin's all along and can move to it.
     */
    public function historyBelongsToTwin(OrgSupplierProduct $drifted, OrgSupplierProduct $twin): bool
    {
        $isTwinParent = fn ($query, string $table) => $query
            ->where(fn ($query) => $query->where("$table.parent_type", 'OrgSupplier')->where("$table.parent_id", $twin->org_supplier_id))
            ->when($twin->org_agent_id, fn ($query) => $query->orWhere(fn ($query) => $query->where("$table.parent_type", 'OrgAgent')->where("$table.parent_id", $twin->org_agent_id)));

        $hasForeignPurchaseOrderLine = PurchaseOrderTransaction::where('purchase_order_transactions.org_supplier_product_id', $drifted->id)
            ->join('purchase_orders', 'purchase_orders.id', 'purchase_order_transactions.purchase_order_id')
            ->leftJoin('agent_supplier_purchase_orders', 'agent_supplier_purchase_orders.id', 'purchase_order_transactions.agent_supplier_purchase_order_id')
            ->where(fn ($query) => $query
                ->whereNot(fn ($query) => $isTwinParent($query, 'purchase_orders'))
                ->orWhere(fn ($query) => $query->where('purchase_orders.parent_type', 'OrgAgent')
                    ->whereRaw('agent_supplier_purchase_orders.supplier_id is distinct from ?', [$twin->supplierProduct->supplier_id])))
            ->exists();

        $hasForeignDeliveryItem = StockDeliveryItem::where('stock_delivery_items.org_supplier_product_id', $drifted->id)
            ->join('stock_deliveries', 'stock_deliveries.id', 'stock_delivery_items.stock_delivery_id')
            ->whereNot(fn ($query) => $isTwinParent($query, 'stock_deliveries'))
            ->exists();

        return !$hasForeignPurchaseOrderLine && !$hasForeignDeliveryItem;
    }

    public function mergeIntoTwin(OrgSupplierProduct $drifted, OrgSupplierProduct $twin, bool $withHistory = false): bool
    {
        return DB::transaction(function () use ($drifted, $twin, $withHistory) {
            if ($withHistory) {
                PurchaseOrderTransaction::where('org_supplier_product_id', $drifted->id)->update(['org_supplier_product_id' => $twin->id]);
                StockDeliveryItem::where('org_supplier_product_id', $drifted->id)->update(['org_supplier_product_id' => $twin->id]);
                ShoppingListItem::withTrashed()->where('org_supplier_product_id', $drifted->id)->update(['org_supplier_product_id' => $twin->id]);
            }

            $twinOrgStockIds = OrgStockHasOrgSupplierProduct::where('org_supplier_product_id', $twin->id)->pluck('org_stock_id')->all();

            OrgStockHasOrgSupplierProduct::where('org_supplier_product_id', $drifted->id)
                ->whereIn('org_stock_id', $twinOrgStockIds)
                ->delete();
            OrgStockHasOrgSupplierProduct::where('org_supplier_product_id', $drifted->id)
                ->update(['org_supplier_product_id' => $twin->id]);

            PurchaseOrderTransaction::where('org_supplier_product_id', $drifted->id)
                ->whereHas('purchaseOrder', fn ($query) => $query->whereIn('state', PurchaseOrderStateEnum::live()))
                ->update(['org_supplier_product_id' => $twin->id]);

            StockDeliveryItem::where('org_supplier_product_id', $drifted->id)
                ->whereHas('stockDelivery', fn ($query) => $query->whereNotIn('state', [
                    StockDeliveryStateEnum::PLACED,
                    StockDeliveryStateEnum::CANCELLED,
                    StockDeliveryStateEnum::NOT_RECEIVED,
                ]))
                ->update(['org_supplier_product_id' => $twin->id]);

            $hasHistory = PurchaseOrderTransaction::where('org_supplier_product_id', $drifted->id)->exists()
                || StockDeliveryItem::where('org_supplier_product_id', $drifted->id)->exists()
                || ShoppingListItem::withTrashed()->where('org_supplier_product_id', $drifted->id)->exists();

            if ($hasHistory) {
                $drifted->update(['state' => OrgSupplierProductStateEnum::DISCONTINUED]);

                return false;
            }

            $drifted->stats()->delete();
            $drifted->delete();

            return true;
        });
    }

    public function getDriftedQuery()
    {
        return OrgSupplierProduct::with('supplierProduct')
            ->join('supplier_products', 'supplier_products.id', '=', 'org_supplier_products.supplier_product_id')
            ->join('org_suppliers', 'org_suppliers.id', '=', 'org_supplier_products.org_supplier_id')
            ->whereColumn('supplier_products.supplier_id', '!=', 'org_suppliers.supplier_id')
            ->select('org_supplier_products.*');
    }

    public function asCommand(Command $command): int
    {
        $fix = (bool)$command->option('fix');

        $withHistory = (bool)$command->option('history');

        $result = $this->handle($fix, $withHistory);

        if ($withHistory) {
            $command->table(['org supplier product', 'organisation', 'code', 'twin', 'outcome', 'purchase order lines', 'delivery items'], $this->historyReport);
            $command->info("History moved: {$result['history_moved']}, kept on the old row: {$result['history_kept']}");
        }

        $command->info($fix ? 'Repointed:' : 'Would repoint:');
        $command->table(
            ['repointed', 'collisions (twin exists)', 'merged and deleted', 'merged and discontinued', 'duplicate copies discontinued', 'unfixable (no single correct org supplier)'],
            [[$result['repointed'], $result['collisions'], $result['merged_deleted'], $result['merged_discontinued'], $result['discontinued_duplicate_copies'], $result['unfixable']]]
        );

        return 0;
    }
}
