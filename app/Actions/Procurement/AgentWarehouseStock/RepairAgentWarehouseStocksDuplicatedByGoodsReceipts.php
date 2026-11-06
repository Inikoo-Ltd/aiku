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
use Illuminate\Support\Facades\File;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The same goods were recorded twice in the agent warehouse: once from the agent's stock sheet and once from a goods
 * receipt against the same purchase order. The goods receipt is the record that holds: its rows are never touched, and
 * every sheet row of the same agent, organisation, product and purchase order is deleted, whatever its units or state.
 * What the sheet said that the receipt does not (packed units, units above the receipt, a container holding the line)
 * is reported so the agent can redo it in aiku. With --fix every deleted row is saved first, whole, under
 * storage/app/repairs, and --agent and --organisation keep a run to one agent or one organisation.
 */
class RepairAgentWarehouseStocksDuplicatedByGoodsReceipts
{
    use AsAction;

    private const float QUANTITY_TOLERANCE = 0.0001;

    public string $commandSignature = 'repair:agent_warehouse_stocks_duplicated_by_goods_receipts
        {--fix : Apply the repair, otherwise dry run}
        {--agent= : Only this agent, by code}
        {--organisation= : Only this organisation, by slug}';

    private ?string $backupFile = null;

    /**
     * @return list<array{agent: string, organisation: string, code: string, purchase_order: ?string, ordered: float, receipt_units: float, sheet_units: float, deleted_sheet_rows: list<int>, notes: ?string}>
     */
    public function handle(bool $fix = false, ?string $agentCode = null, ?string $organisationSlug = null): array
    {
        $this->backupFile = $fix ? storage_path('app/repairs/agent-warehouse-stocks-duplicated-by-goods-receipts/'.now()->format('Ymd-His').'.jsonl') : null;

        return $this->duplicatedGroups($agentCode, $organisationSlug)->map(fn (object $group) => $fix
            ? DB::transaction(fn () => $this->repairGroup($group, true))
            : $this->repairGroup($group, false))->all();
    }

    private function duplicatedGroups(?string $agentCode, ?string $organisationSlug): Collection
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
            ->when($agentCode, fn ($query) => $query->where('agents.code', $agentCode))
            ->when($organisationSlug, fn ($query) => $query->where('organisations.slug', $organisationSlug))
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
     * @return array{agent: string, organisation: string, code: string, purchase_order: ?string, ordered: float, receipt_units: float, sheet_units: float, deleted_sheet_rows: list<int>, notes: ?string}
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
        $packedUnits  = round((float) $sheetRows->filter(fn (AgentWarehouseStock $row) => $row->state !== AgentWarehouseStockStateEnum::RECEIVED)->sum('quantity'), 3);

        if ($fix && $receiptRows->isNotEmpty() && $sheetRows->isNotEmpty()) {
            $this->backUp($sheetRows);
            AgentWarehouseStock::whereIn('id', $sheetRows->pluck('id'))->whereNull('agent_goods_receipt_id')->delete();
        }

        $containers = $this->containersOfGroup($group);

        return [
            'agent'              => $group->agent_code,
            'organisation'       => $group->organisation_slug,
            'code'               => $group->code,
            'purchase_order'     => $group->purchase_order_reference,
            'ordered'            => $this->orderedUnits($group),
            'receipt_units'      => $receiptUnits,
            'sheet_units'        => $sheetUnits,
            'deleted_sheet_rows' => $receiptRows->isNotEmpty() ? $sheetRows->pluck('id')->values()->all() : [],
            'notes'              => collect([
                $packedUnits > self::QUANTITY_TOLERANCE ? "sheet had $packedUnits packed" : null,
                $sheetUnits > $receiptUnits + self::QUANTITY_TOLERANCE ? 'sheet had '.round($sheetUnits - $receiptUnits, 3).' more than the receipt' : null,
                $containers->isNotEmpty() ? 'in container '.$containers->implode(', ') : null,
            ])->filter()->implode('; ') ?: null,
        ];
    }

    /**
     * Every sheet row is written out whole before it is deleted, one JSON line each, so a repair can be checked and
     * undone after the terminal output is gone.
     *
     * @param  Collection<int, AgentWarehouseStock>  $sheetRows
     */
    private function backUp(Collection $sheetRows): void
    {
        File::ensureDirectoryExists(dirname($this->backupFile));
        File::append($this->backupFile, $sheetRows->map(fn (AgentWarehouseStock $row) => json_encode($row->getRawOriginal())."\n")->implode(''));
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
        $results = $this->handle($fix, $command->option('agent'), $command->option('organisation'));

        $command->table(
            ['Agent', 'Org', 'Code', 'PO', 'Ordered', 'Receipt', 'Sheet', 'Deleted sheet rows', 'Notes'],
            array_map(fn (array $row) => [
                $row['agent'],
                $row['organisation'],
                $row['code'],
                $row['purchase_order'],
                $row['ordered'],
                $row['receipt_units'],
                $row['sheet_units'],
                implode(',', $row['deleted_sheet_rows']),
                $row['notes'],
            ], $results)
        );

        $command->info(sprintf(
            '%s: %d products, %d sheet rows %s, %d with notes',
            $fix ? 'Repaired' : 'Dry run',
            count($results),
            collect($results)->sum(fn (array $row) => count($row['deleted_sheet_rows'])),
            $fix ? 'deleted' : 'to delete',
            collect($results)->whereNotNull('notes')->count(),
        ));

        if ($this->backupFile && File::exists($this->backupFile)) {
            $command->info("Deleted rows saved to {$this->backupFile}");
        }

        return 0;
    }
}
