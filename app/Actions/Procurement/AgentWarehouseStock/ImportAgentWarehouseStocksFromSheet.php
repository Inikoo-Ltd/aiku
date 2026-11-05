<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 14 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\AgentWarehouseStock;

use App\Enums\Procurement\AgentWarehouseStock\AgentWarehouseStockStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\AgentWarehouseStock;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Replaces an agent's warehouse stock for one organisation, except the goods received line by line on the In production page, with the Moving Stock and Packed tabs of its stock spreadsheet (HELP-3960).
 * The In Production tab holds what the suppliers are still making, so the open purchase order of each of its lines is marked
 * as in production instead (HELP-4025).
 */
class ImportAgentWarehouseStocksFromSheet
{
    use AsAction;

    public string $commandSignature = 'agent:import_warehouse_stocks {agent : agent organisation slug} {organisation : organisation the stock is for} {sheet : Google Sheet id, shared by link} {--dry-run}';

    private const array TABS = [
        'Moving Stock'     => AgentWarehouseStockStateEnum::RECEIVED,
        'Packed Already'   => AgentWarehouseStockStateEnum::PACKED_FOR_NEXT_CONTAINER,
        'Packed Already 2' => AgentWarehouseStockStateEnum::PACKED_FOR_LATER_CONTAINER,
    ];

    private const array TAB_ALIASES = [
        'Packed Already 2' => ['Copy of Packed Already'],
    ];

    private const string PRODUCTION_TAB = 'In Production';

    public const array CLOSED_ORDER_STATES = ['settled', 'cancelled', 'not_received'];

    private const int NOTES = 0;
    private const int CODE = 2;
    private const int UNITS = 7;
    private const int PO_NUMBER = 26;

    /**
     * @return array{rows: list<array<string, mixed>>, unmatched: list<string>, in_production: list<array{code: string, units: float, purchase_order: ?string}>}
     */
    public function handle(Agent $agent, Organisation $organisation, string $sheetId, bool $dryRun = false): array
    {
        $rows      = [];
        $unmatched = [];
        $sheetTabs = $this->tabNames($sheetId);
        $receipts  = $this->goodsReceiptsByProductAndOrder($agent, $organisation);

        $inProduction  = [];
        $productionTab = collect($sheetTabs)->first(fn (string $name) => strcasecmp(trim($name), self::PRODUCTION_TAB) === 0);
        foreach ($productionTab ? $this->readTab($sheetId, $productionTab) : [] as $line) {
            $rawCode = trim($line[self::CODE] ?? '');
            if ($rawCode === '' || strcasecmp($rawCode, 'CODE') === 0 || $this->isExtraCarton($rawCode)) {
                continue;
            }

            $code          = trim(preg_replace('/\s*\(A\)$/i', '', $rawCode));
            $purchaseOrder = $this->openOrderWithProduct($organisation, $this->candidates($agent, $code), $this->orderIds($organisation, trim($line[self::PO_NUMBER] ?? '')));
            $inProduction[] = ['code' => $code, 'units' => (float) str_replace(',', '', $line[self::UNITS] ?? ''), 'purchase_order' => $purchaseOrder?->reference];
            if (!$dryRun && $purchaseOrder) {
                ImportAgentWarehouseStocksFromContainerList::make()->markInProduction($purchaseOrder, "$agent->code stock spreadsheet: in the In Production tab, still being made by the supplier");
            }
        }

        foreach (self::TABS as $tab => $state) {
            $names    = array_map('mb_strtolower', [$tab, ...(self::TAB_ALIASES[$tab] ?? [])]);
            $sheetTab = collect($sheetTabs)->first(fn (string $name) => in_array(mb_strtolower(trim($name)), $names, true));
            if ($sheetTab === null) {
                $unmatched[] = "$tab: tab not in the sheet";
                continue;
            }

            foreach ($this->readTab($sheetId, $sheetTab) as $line) {
                $rawCode = trim($line[self::CODE] ?? '');
                if ($rawCode === '' || strcasecmp($rawCode, 'CODE') === 0 || $this->isExtraCarton($rawCode)) {
                    continue;
                }

                $code  = trim(preg_replace('/\s*\(A\)$/i', '', $rawCode));
                $units = (float) str_replace(',', '', $line[self::UNITS] ?? '');

                $candidates = $this->candidates($agent, $code);
                $orderIds        = $this->orderIds($organisation, $po = trim($line[self::PO_NUMBER] ?? ''));
                $supplierProduct = $candidates->first(fn (SupplierProduct $candidate) => $this->orderWithLine($orderIds, $candidate)) ?? $candidates->first();

                if (!$supplierProduct || $units <= 0) {
                    $unmatched[] = "$tab: $code".($supplierProduct ? ' (no units)' : '');
                    continue;
                }

                $orderId = ($purchaseOrderId = $this->orderWithLine($orderIds, $supplierProduct) ?? $this->orderOfSupplier($orderIds, $supplierProduct)) ?? $this->latestOrderWithProduct($organisation, $candidates);
                if ($receipt = $receipts->get("$supplierProduct->id|$orderId")) {
                    $unmatched[] = "$tab: $code already received through $receipt";
                    continue;
                }

                $rows[] = [
                    'group_id'            => $agent->group_id,
                    'agent_id'            => $agent->id,
                    'organisation_id'     => $organisation->id,
                    'supplier_product_id' => $supplierProduct->id,
                    'state'               => $state->value,
                    'quantity'            => $units,
                    'purchase_order_id'   => $orderId,
                    'notes'               => collect([trim($line[self::NOTES] ?? ''), $po && !$purchaseOrderId ? "PO: $po" : ''])->filter()->implode(' · ') ?: null,
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ];
            }
        }

        if (!$dryRun) {
            DB::transaction(function () use ($agent, $organisation, $rows) {
                DB::table('agents')->where('id', $agent->id)->lockForUpdate()->value('id');
                $receipts = $this->goodsReceiptsByProductAndOrder($agent, $organisation);
                $rows     = array_values(array_filter($rows, fn (array $row) => !$receipts->has("{$row['supplier_product_id']}|{$row['purchase_order_id']}")));

                AgentWarehouseStock::where('agent_id', $agent->id)->where('organisation_id', $organisation->id)->whereNull('purchase_order_transaction_id')->delete();
                AgentWarehouseStock::insert($rows);
            });
        }

        return ['rows' => $rows, 'unmatched' => $unmatched, 'in_production' => $inProduction];
    }

    /**
     * The goods receipt is the record that holds (INI-204): a sheet line for a product and purchase order the agent already
     * received through a goods receipt is the same goods, so it is not imported again.
     *
     * @return SupportCollection<string, string> goods receipt references keyed by "supplier product id|purchase order id"
     */
    private function goodsReceiptsByProductAndOrder(Agent $agent, Organisation $organisation): SupportCollection
    {
        return DB::table('agent_warehouse_stocks')
            ->join('agent_goods_receipts', 'agent_goods_receipts.id', 'agent_warehouse_stocks.agent_goods_receipt_id')
            ->where('agent_warehouse_stocks.agent_id', $agent->id)
            ->where('agent_warehouse_stocks.organisation_id', $organisation->id)
            ->where('agent_warehouse_stocks.quantity', '>', 0)
            ->whereNotNull('agent_warehouse_stocks.purchase_order_id')
            ->get(['agent_warehouse_stocks.supplier_product_id', 'agent_warehouse_stocks.purchase_order_id', 'agent_goods_receipts.reference'])
            ->groupBy(fn (object $row) => "$row->supplier_product_id|$row->purchase_order_id")
            ->map(fn ($group) => $group->pluck('reference')->unique()->implode(', '));
    }

    /**
     * @return Collection<int, SupplierProduct>
     */
    private function candidates(Agent $agent, string $code): Collection
    {
        return SupplierProduct::where('agent_id', $agent->id)
            ->whereRaw('lower(code) = ?', [mb_strtolower($code)])
            ->orderByRaw('(select count(*) from purchase_order_transactions where supplier_product_id = supplier_products.id) desc, id')
            ->get();
    }

    /**
     * The submitted or confirmed purchase order of the organisation holding the product, the one the sheet names first, else the newest.
     *
     * @param  Collection<int, SupplierProduct>  $candidates
     * @param  list<int>  $orderIds
     */
    private function openOrderWithProduct(Organisation $organisation, Collection $candidates, array $orderIds): ?PurchaseOrder
    {
        return PurchaseOrder::where('organisation_id', $organisation->id)
            ->whereIn('state', [PurchaseOrderStateEnum::SUBMITTED, PurchaseOrderStateEnum::CONFIRMED])
            ->whereHas('purchaseOrderTransactions', fn ($query) => $query->whereIn('supplier_product_id', $candidates->pluck('id')))
            ->orderByRaw('id = any(?::int[]) desc', ['{'.implode(',', $orderIds).'}'])
            ->orderByDesc('id')
            ->first();
    }

    /** @var array<string, list<int>> */
    private array $purchaseOrderIds = [];

    /**
     * The sheet writes the agent's orders as "PO-230 UK", "PO-61 ES" or just "PO-050", aiku as AWIndo-230-UK or AWIndo-061-ES 26.08.26.
     * Since INI-059 such an order is split into one purchase order per supplier (JMS-AWIndo-136-SK...) and the original
     * is soft deleted with data->split_into. A row links to the split holding a line of its product, else to the split of
     * its product's supplier. The same product can be recorded twice under one agent (ACShop-12 under GA and under Gloria,
     * both Gloria Art), so the product on the order wins over its twin.
     *
     * @return list<int>
     */
    private function orderIds(Organisation $organisation, string $poNumber): array
    {
        if (!preg_match('/PO\s*-?\s*0*(\d+)\b/i', $poNumber, $match)) {
            return [];
        }

        return $this->purchaseOrderIds["$organisation->id|$match[1]"] ??= $this->liveOrderIds($organisation, (int) $match[1]);
    }

    /**
     * @param  list<int>  $orderIds
     */
    private function orderWithLine(array $orderIds, SupplierProduct $supplierProduct): ?int
    {
        return $orderIds ? DB::table('purchase_order_transactions')
            ->whereIn('purchase_order_id', $orderIds)
            ->where('supplier_product_id', $supplierProduct->id)
            ->orderBy('purchase_order_id')
            ->value('purchase_order_id') : null;
    }

    /**
     * The UK runs each Indo cycle as a numbered order plus Production / Warehouse / Packing orders, and deletes the drafts
     * when the next cycle starts, so the order the sheet names is often gone. Then the row links to the newest open Indo
     * order of the organisation holding the product, and the sheet's order stays in the notes. A settled, cancelled or
     * not received order is never guessed: its goods already left or never came, so the stock on the sheet is for
     * another order (RRBud-13 packed for a new order was linked to AWIndo-43, shipped months before).
     *
     * @param  Collection<int, SupplierProduct>  $candidates
     */
    private function latestOrderWithProduct(Organisation $organisation, Collection $candidates): ?int
    {
        return DB::table('purchase_orders')
            ->join('purchase_order_transactions', 'purchase_order_transactions.purchase_order_id', 'purchase_orders.id')
            ->where('purchase_orders.organisation_id', $organisation->id)
            ->where('purchase_orders.reference', 'ilike', '%indo%')
            ->whereNull('purchase_orders.deleted_at')
            ->whereIn('purchase_order_transactions.supplier_product_id', $candidates->pluck('id'))
            ->whereNotIn('purchase_orders.state', self::CLOSED_ORDER_STATES)
            ->orderByDesc('purchase_orders.id')
            ->value('purchase_orders.id');
    }

    /**
     * @param  list<int>  $orderIds
     */
    private function orderOfSupplier(array $orderIds, SupplierProduct $supplierProduct): ?int
    {
        return $orderIds ? DB::table('purchase_orders')
            ->join('org_suppliers', 'org_suppliers.id', 'purchase_orders.parent_id')
            ->where('purchase_orders.parent_type', 'OrgSupplier')
            ->whereIn('purchase_orders.id', $orderIds)
            ->where('org_suppliers.supplier_id', $supplierProduct->supplier_id)
            ->value('purchase_orders.id') : null;
    }

    /**
     * Every order of the organisation numbered so, whatever the naming of its time: AWIndo-136-SK, AWIndo165_UK,
     * AWIndo-12-ES, AWIndo-36-VAL250180. Deleted ones count for their splits.
     *
     * @return list<int>
     */
    private function liveOrderIds(Organisation $organisation, int $number): array
    {
        $orders = DB::table('purchase_orders')
            ->where('organisation_id', $organisation->id)
            ->whereRaw('reference ~* ?', ['^AWIndo[-_ ]?0*'.$number.'([^0-9]|$)'])
            ->get(['id', 'deleted_at', 'data']);

        $ids = $orders->flatMap(fn ($order) => json_decode($order->data ?? '{}', true)['split_into'] ?? [$order->id])->unique()->all();

        return DB::table('purchase_orders')->whereIn('id', $ids)->whereNull('deleted_at')->pluck('id')->all();
    }

    /**
     * A product shipped in several cartons has one row per carton: the main or (A) row holds the units, while
     * "PART OF" and (B), (C)... rows are the other cartons and never add stock, even when units were typed in.
     */
    private function isExtraCarton(string $code): bool
    {
        return (bool) preg_match('/PART OF|\((?!A\))[A-Z]\)$/i', $code);
    }

    /**
     * The gviz endpoint answers an unknown tab with the first one, so tabs are looked up by name first.
     *
     * @return list<string>
     */
    private function tabNames(string $sheetId): array
    {
        $html = Http::get("https://docs.google.com/spreadsheets/d/$sheetId/htmlview")->throw()->body();
        preg_match_all('/\{name: "((?:[^"\\\\]|\\\\.)*)"/', $html, $matches);

        return array_map('stripcslashes', $matches[1]);
    }

    /**
     * @return list<list<string>>
     */
    private function readTab(string $sheetId, string $tab): array
    {
        $csv = Http::get("https://docs.google.com/spreadsheets/d/$sheetId/gviz/tq", ['tqx' => 'out:csv', 'sheet' => $tab])->throw()->body();

        $lines = [];
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $csv);
        rewind($stream);
        fgetcsv($stream, escape: '\\');
        while (($line = fgetcsv($stream, escape: '\\')) !== false) {
            $lines[] = $line;
        }
        fclose($stream);

        return $lines;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $agent = Organisation::where('slug', $command->argument('agent'))->firstOrFail()->agent;
        if (!$agent) {
            $command->error('Not an agent organisation');

            return 1;
        }

        $organisation = Organisation::where('slug', $command->argument('organisation'))->firstOrFail();
        $result       = $this->handle($agent, $organisation, $command->argument('sheet'), (bool) $command->option('dry-run'));

        $command->info(count($result['rows']).' rows '.($command->option('dry-run') ? 'would be imported' : 'imported'));
        foreach ($result['in_production'] as $line) {
            $command->line("In production: {$line['code']} {$line['units']} units, purchase order ".($line['purchase_order'] ?? 'none open'));
        }
        foreach ($result['unmatched'] as $line) {
            $command->warn("Skipped $line");
        }

        return 0;
    }
}
