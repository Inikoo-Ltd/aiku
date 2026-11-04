<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-04 15:39
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\AgentWarehouseStock;

use App\Actions\GoodsIn\StockDelivery\StoreStockDeliveryFromPurchaseOrder;
use App\Enums\Procurement\AgentWarehouseStock\AgentWarehouseStockStateEnum;
use App\Models\Procurement\AgentWarehouseStock;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The same goods were recorded twice in the agent warehouse: once from the agent's stock sheet and once from a goods
 * receipt against the same purchase order. The goods receipt rows are never touched, as they hold the purchase order
 * line and the receipt check. The sheet rows of the same agent, organisation, product and purchase order lose the units
 * the receipts already hold, and are deleted when nothing is left.
 *
 * Only plain duplicates are repaired: every sheet row still received, and no line of the order for that product in a
 * container. A packed sheet row or a line already in a container means the sheet knows something the receipt does not,
 * so those are listed to be settled by hand.
 */
class RepairAgentWarehouseStocksDuplicatedByGoodsReceipts
{
    use AsAction;

    private const float QUANTITY_TOLERANCE = 0.0001;

    public string $commandSignature = 'repair:agent_warehouse_stocks_duplicated_by_goods_receipts {--fix : Apply the repair, otherwise dry run}';

    /**
     * @return list<array{agent: string, organisation: string, code: string, purchase_order: ?string, ordered: float, receipt_units: float, sheet_units: float, sheet_units_removed: float, sheet_units_left: float, deleted_sheet_rows: list<int>, reduced_sheet_rows: list<int>, skipped: ?string, needs_check: ?string}>
     */
    public function handle(bool $fix = false): array
    {
        return $this->duplicatedGroups()->map(fn (object $group) => $fix
            ? DB::transaction(fn () => $this->repairGroup($group, true))
            : $this->repairGroup($group, false))->all();
    }

    private function duplicatedGroups(): Collection
    {
        return DB::table('agent_warehouse_stocks as sheet')
            ->join('agent_warehouse_stocks as receipt', fn ($join) => $join
                ->on('receipt.agent_id', 'sheet.agent_id')
                ->on('receipt.organisation_id', 'sheet.organisation_id')
                ->on('receipt.supplier_product_id', 'sheet.supplier_product_id')
                ->on('receipt.purchase_order_id', 'sheet.purchase_order_id')
                ->whereNotNull('receipt.agent_goods_receipt_id')
                ->where('receipt.quantity', '>', 0))
            ->join('agents', 'agents.id', 'sheet.agent_id')
            ->join('organisations', 'organisations.id', 'sheet.organisation_id')
            ->join('supplier_products', 'supplier_products.id', 'sheet.supplier_product_id')
            ->join('purchase_orders', 'purchase_orders.id', 'sheet.purchase_order_id')
            ->whereNull('sheet.agent_goods_receipt_id')
            ->where('sheet.quantity', '>', 0)
            ->select([
                'sheet.agent_id',
                'sheet.organisation_id',
                'sheet.supplier_product_id',
                'sheet.purchase_order_id',
                'agents.code as agent_code',
                'organisations.slug as organisation_slug',
                'supplier_products.code',
                'purchase_orders.reference as purchase_order_reference',
            ])
            ->distinct()
            ->orderBy('agents.code')
            ->orderBy('organisations.slug')
            ->orderBy('purchase_orders.reference')
            ->orderBy('supplier_products.code')
            ->get();
    }

    /**
     * @return array{agent: string, organisation: string, code: string, purchase_order: ?string, ordered: float, receipt_units: float, sheet_units: float, sheet_units_removed: float, sheet_units_left: float, deleted_sheet_rows: list<int>, reduced_sheet_rows: list<int>, skipped: ?string, needs_check: ?string}
     */
    private function repairGroup(object $group, bool $fix): array
    {
        $rows = AgentWarehouseStock::where('agent_id', $group->agent_id)
            ->where('organisation_id', $group->organisation_id)
            ->where('supplier_product_id', $group->supplier_product_id)
            ->where('purchase_order_id', $group->purchase_order_id)
            ->where('quantity', '>', 0)
            ->orderBy('id')
            ->when($fix, fn ($query) => $query->lockForUpdate())
            ->get();

        $receiptRows = $rows->whereNotNull('agent_goods_receipt_id');
        $sheetRows   = $rows->whereNull('agent_goods_receipt_id');

        $receiptUnits = round((float) $receiptRows->sum('quantity'), 3);
        $sheetUnits   = round((float) $sheetRows->sum('quantity'), 3);
        $skipped      = $this->skipReason($group, $sheetRows);

        $unitsToRemove = $skipped ? 0 : $receiptUnits;
        $removedUnits  = 0;
        $deletedRows   = [];
        $reducedRows   = [];

        foreach ($sheetRows as $sheetRow) {
            if ($unitsToRemove <= self::QUANTITY_TOLERANCE) {
                break;
            }

            $quantity      = (float) $sheetRow->quantity;
            $removed       = min($quantity, $unitsToRemove);
            $left          = round($quantity - $removed, 3);
            $unitsToRemove = round($unitsToRemove - $removed, 3);
            $removedUnits  = round($removedUnits + $removed, 3);

            if ($left <= self::QUANTITY_TOLERANCE) {
                $deletedRows[] = $sheetRow->id;
                if ($fix) {
                    $sheetRow->delete();
                }
            } else {
                $reducedRows[] = $sheetRow->id;
                if ($fix) {
                    $sheetRow->update(['quantity' => $left]);
                }
            }
        }

        return [
            'agent'               => $group->agent_code,
            'organisation'        => $group->organisation_slug,
            'code'                => $group->code,
            'purchase_order'      => $group->purchase_order_reference,
            'ordered'             => $this->orderedUnits($group),
            'receipt_units'       => $receiptUnits,
            'sheet_units'         => $sheetUnits,
            'sheet_units_removed' => $removedUnits,
            'sheet_units_left'    => round($sheetUnits - $removedUnits, 3),
            'deleted_sheet_rows'  => $deletedRows,
            'reduced_sheet_rows'  => $reducedRows,
            'skipped'             => $skipped,
            'needs_check'         => !$skipped && $sheetUnits > $receiptUnits + self::QUANTITY_TOLERANCE ? 'sheet had more units than the receipt: the rest is kept' : null,
        ];
    }

    /**
     * @param  Collection<int, AgentWarehouseStock>  $sheetRows
     */
    private function skipReason(object $group, Collection $sheetRows): ?string
    {
        $containers = $this->containersOfGroup($group);
        if ($containers->isNotEmpty()) {
            return 'line already in container '.$containers->implode(', ');
        }

        $packedUnits = $sheetRows->filter(fn (AgentWarehouseStock $row) => $row->state !== AgentWarehouseStockStateEnum::RECEIVED)->sum('quantity');
        if ($packedUnits > self::QUANTITY_TOLERANCE) {
            return 'sheet has '.round((float) $packedUnits, 3).' units packed';
        }

        return null;
    }

    private function containersOfGroup(object $group): Collection
    {
        return StoreStockDeliveryFromPurchaseOrder::liveDeliveryItemsOfTransaction(DB::query())
            ->join('purchase_order_transactions', fn ($join) => $join
                ->where('purchase_order_transactions.purchase_order_id', $group->purchase_order_id)
                ->where('purchase_order_transactions.supplier_product_id', $group->supplier_product_id))
            ->join('stock_deliveries', 'stock_deliveries.id', 'stock_delivery_items.stock_delivery_id')
            ->whereNull('stock_deliveries.deleted_at')
            ->distinct()
            ->pluck('stock_deliveries.reference');
    }

    private function orderedUnits(object $group): float
    {
        return (float) DB::table('purchase_order_transactions')
            ->where('purchase_order_id', $group->purchase_order_id)
            ->where('supplier_product_id', $group->supplier_product_id)
            ->sum('quantity_ordered');
    }

    public function asCommand(Command $command): int
    {
        $fix     = (bool) $command->option('fix');
        $results = $this->handle($fix);

        $command->table(
            ['Agent', 'Org', 'Code', 'PO', 'Ordered', 'Receipt', 'Sheet', 'Removed', 'Left', 'Deleted', 'Reduced', 'Skipped', 'Check'],
            array_map(fn (array $row) => [
                $row['agent'],
                $row['organisation'],
                $row['code'],
                $row['purchase_order'],
                $row['ordered'],
                $row['receipt_units'],
                $row['sheet_units'],
                $row['sheet_units_removed'],
                $row['sheet_units_left'],
                implode(',', $row['deleted_sheet_rows']),
                implode(',', $row['reduced_sheet_rows']),
                $row['skipped'],
                $row['needs_check'],
            ], $results)
        );

        $command->info(sprintf(
            '%s: %d products, %d sheet rows deleted, %d reduced, %d skipped, %d to check',
            $fix ? 'Repaired' : 'Dry run',
            count($results),
            collect($results)->sum(fn (array $row) => count($row['deleted_sheet_rows'])),
            collect($results)->sum(fn (array $row) => count($row['reduced_sheet_rows'])),
            collect($results)->whereNotNull('skipped')->count(),
            collect($results)->whereNotNull('needs_check')->count(),
        ));

        return 0;
    }
}
