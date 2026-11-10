<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-10 16:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\AgentWarehouseStock;

use App\Actions\Procurement\AgentWarehouseStock\UI\GetAgentWarehouseStockPurchaseOrderLines;
use App\Models\Procurement\AgentWarehouseStock;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Lines imported from the stock sheet know their purchase order but never its line, so they can not go in a container,
 * which is filled by line. When the order is still open, was named by the sheet (not guessed) and has exactly one open
 * line of the product, not received through a goods receipt, that line is the one: the row is linked to it. Every other
 * row is left for the agent to link by hand (Link to correct PO). Linking must not change what the agent sees as held,
 * so a line that already has units in a container is left alone: those units would be taken off the row
 * (AgentWarehouseStock::heldQuantitySql). Nothing else is recorded on the order (no automatic hand over). With --fix
 * each row is saved before it is changed, one JSON line each under storage/app/repairs, so the repair can be checked
 * and undone.
 */
class RepairAgentWarehouseStocksMissingPurchaseOrderLine
{
    use AsAction;

    public string $commandSignature = 'repair:agent_warehouse_stocks_missing_purchase_order_line
        {--fix : Apply the repair, otherwise dry run}
        {--agent= : Only this agent, by code}
        {--organisation= : Only this organisation, by slug}';

    private ?string $backupFile = null;

    /**
     * @return list<array{id: int, agent: string, organisation: string, code: string, purchase_order: string, units: float, line: ?int, skipped: ?string}>
     */
    public function handle(bool $fix = false, ?string $agentCode = null, ?string $organisationSlug = null): array
    {
        $this->backupFile = $fix ? storage_path('app/repairs/agent-warehouse-stocks-missing-purchase-order-line/'.now()->format('Ymd-His').'.jsonl') : null;

        return $this->rowsWithoutLine($agentCode, $organisationSlug)
            ->map(fn (AgentWarehouseStock $stock) => $fix
                ? DB::transaction(fn () => $this->repairRow($stock, true))
                : $this->repairRow($stock, false))
            ->all();
    }

    /**
     * @return Collection<int, AgentWarehouseStock>
     */
    private function rowsWithoutLine(?string $agentCode, ?string $organisationSlug): Collection
    {
        return AgentWarehouseStock::query()
            ->join('agents', 'agents.id', 'agent_warehouse_stocks.agent_id')
            ->join('organisations', 'organisations.id', 'agent_warehouse_stocks.organisation_id')
            ->join('supplier_products', 'supplier_products.id', 'agent_warehouse_stocks.supplier_product_id')
            ->join('purchase_orders', 'purchase_orders.id', 'agent_warehouse_stocks.purchase_order_id')
            ->whereNull('agent_warehouse_stocks.purchase_order_transaction_id')
            ->whereNull('agent_warehouse_stocks.agent_goods_receipt_id')
            ->where('agent_warehouse_stocks.quantity', '>', 0)
            ->when($agentCode, fn ($query) => $query->where('agents.code', $agentCode))
            ->when($organisationSlug, fn ($query) => $query->where('organisations.slug', $organisationSlug))
            ->select([
                'agent_warehouse_stocks.*',
                'agents.code as agent_code',
                'organisations.slug as organisation_slug',
                'supplier_products.code as supplier_product_code',
                'purchase_orders.reference as purchase_order_reference',
            ])
            ->orderBy('agents.code')
            ->orderBy('organisations.slug')
            ->orderBy('purchase_orders.reference')
            ->orderBy('supplier_products.code')
            ->get();
    }

    /**
     * @return array{id: int, agent: string, organisation: string, code: string, purchase_order: string, purchase_order_id: int, units: float, line: ?int, skipped: ?string}
     */
    private function repairRow(AgentWarehouseStock $stock, bool $fix): array
    {
        $result = [
            'id'                => $stock->id,
            'agent'             => $stock->agent_code,
            'organisation'      => $stock->organisation_slug,
            'code'              => $stock->supplier_product_code,
            'purchase_order'    => $stock->purchase_order_reference,
            'purchase_order_id' => $stock->purchase_order_id,
            'units'             => (float) $stock->quantity,
            'line'              => null,
            'skipped'           => null,
        ];

        if (preg_match('/(?:^| · )PO: /u', (string) $stock->notes)) {
            return [...$result, 'skipped' => 'guessed order'];
        }

        $lines = GetAgentWarehouseStockPurchaseOrderLines::linkableLines($stock)
            ->where('purchase_order_transactions.purchase_order_id', $stock->purchase_order_id)
            ->select(['purchase_order_transactions.id', DB::raw(GetAgentWarehouseStockPurchaseOrderLines::hasGoodsReceiptSql().' as has_goods_receipt')])
            ->get();

        $skipped = match (true) {
            $lines->isEmpty()                 => 'no open line of the product on this order',
            $lines->count() > 1               => 'several lines of the product on this order',
            (bool) $lines->first()->has_goods_receipt => 'line already received through a goods receipt',
            $this->unitsInContainers($lines->first()->id) > 0 => 'line already has units in a container',
            default                           => null,
        };

        if ($skipped) {
            return [...$result, 'skipped' => $skipped];
        }

        $lineId = $lines->first()->id;

        if ($fix) {
            $locked = AgentWarehouseStock::whereKey($stock->id)->whereNull('purchase_order_transaction_id')->lockForUpdate()->first();
            if (!$locked) {
                return [...$result, 'skipped' => 'linked meanwhile'];
            }

            $this->backUp($locked);
            $locked->update(['purchase_order_transaction_id' => $lineId]);
        }

        return [...$result, 'line' => $lineId];
    }

    private function unitsInContainers(int $purchaseOrderTransactionId): float
    {
        return (float) DB::table('stock_delivery_items')
            ->join('stock_deliveries', 'stock_deliveries.id', 'stock_delivery_items.stock_delivery_id')
            ->whereRaw("(stock_delivery_items.data->>'purchase_order_transaction_id')::bigint = ?", [$purchaseOrderTransactionId])
            ->whereNull('stock_delivery_items.deleted_at')
            ->whereNull('stock_deliveries.deleted_at')
            ->whereNotIn('stock_delivery_items.state', ['cancelled', 'not_received'])
            ->sum('stock_delivery_items.unit_quantity');
    }

    private function backUp(AgentWarehouseStock $stock): void
    {
        File::ensureDirectoryExists(dirname($this->backupFile));
        File::append($this->backupFile, json_encode($stock->getRawOriginal())."\n");
    }

    public function asCommand(Command $command): int
    {
        $fix     = (bool) $command->option('fix');
        $results = collect($this->handle($fix, $command->option('agent'), $command->option('organisation')));

        $command->table(
            ['Row', 'Agent', 'Org', 'Code', 'PO', 'Units', 'PO line', 'Skipped'],
            $results->map(fn (array $row) => [
                $row['id'],
                $row['agent'],
                $row['organisation'],
                $row['code'],
                $row['purchase_order'],
                $row['units'],
                $row['line'],
                $row['skipped'],
            ])->all()
        );

        $command->info(sprintf(
            '%s: %d rows %s, %d skipped',
            $fix ? 'Repaired' : 'Dry run',
            $results->whereNotNull('line')->count(),
            $fix ? 'linked to their purchase order line' : 'to link to their purchase order line',
            $results->whereNotNull('skipped')->count(),
        ));

        $results->whereNotNull('skipped')->countBy('skipped')->each(fn (int $count, string $reason) => $command->line("  $reason: $count"));

        if ($this->backupFile && File::exists($this->backupFile)) {
            $command->info("Rows before the repair saved to {$this->backupFile}");
        }

        return 0;
    }
}
