<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-10 14:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\AgentWarehouseStock\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Models\Procurement\AgentWarehouseStock;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

/**
 * The open purchase order lines a line of the agent's warehouse imported from the stock sheet can belong to: the same
 * product, ordered by the same organisation through this agent, on an order submitted or confirmed. A line already
 * received through a goods receipt is listed but can not be chosen, as the receipt is the record that holds its goods.
 */
class GetAgentWarehouseStockPurchaseOrderLines extends OrgAction
{
    use WithAgentOrganisation;
    use WithProcurementAuthorisation;


    public const array LINKABLE_LINE_STATES = [PurchaseOrderTransactionStateEnum::SUBMITTED, PurchaseOrderTransactionStateEnum::CONFIRMED];

    public static function linkableLines(AgentWarehouseStock $stock): Builder
    {
        return PurchaseOrderTransaction::query()
            ->join('purchase_orders', 'purchase_orders.id', 'purchase_order_transactions.purchase_order_id')
            ->where('purchase_order_transactions.organisation_id', $stock->organisation_id)
            ->where('purchase_order_transactions.supplier_product_id', $stock->supplier_product_id)
            ->where('purchase_orders.agent_id', $stock->agent_id)
            ->whereNull('purchase_orders.deleted_at')
            ->whereNull('purchase_order_transactions.deleted_at')
            ->whereIn('purchase_orders.state', PurchaseOrderStateEnum::openValues())
            ->whereIn('purchase_order_transactions.state', array_map(fn (PurchaseOrderTransactionStateEnum $state) => $state->value, self::LINKABLE_LINE_STATES));
    }

    public static function hasGoodsReceiptSql(): string
    {
        return 'exists(select 1 from agent_warehouse_stocks as receipts where receipts.purchase_order_transaction_id = purchase_order_transactions.id and receipts.agent_goods_receipt_id is not null)';
    }

    /**
     * @return list<array{id: int, purchase_order_id: int, purchase_order_slug: string, purchase_order_reference: string, purchase_order_state: string, purchase_order_date: ?string, is_current: bool, quantity_ordered: float, quantity_at_agent: float, has_goods_receipt: bool, deposit_amount: ?float, deposit_paid_at: ?string, currency_code: ?string}>
     */
    public function handle(AgentWarehouseStock $stock): array
    {
        return self::linkableLines($stock)
            ->leftJoin('currencies', 'currencies.id', 'purchase_orders.currency_id')
            ->orderByDesc('purchase_orders.date')
            ->orderByDesc('purchase_orders.id')
            ->select([
                'purchase_order_transactions.id',
                'purchase_order_transactions.quantity_ordered',
                'purchase_orders.id as purchase_order_id',
                'purchase_orders.slug as purchase_order_slug',
                'purchase_orders.reference as purchase_order_reference',
                'purchase_orders.deposit_amount',
                'purchase_orders.deposit_paid_at',
                'currencies.code as currency_code',
                'purchase_orders.state as purchase_order_state',
                'purchase_orders.date as purchase_order_date',
                DB::raw('(select coalesce(sum(held.quantity), 0) from agent_warehouse_stocks as held where held.purchase_order_transaction_id = purchase_order_transactions.id) as quantity_at_agent'),
                DB::raw(self::hasGoodsReceiptSql().' as has_goods_receipt'),
            ])
            ->get()
            ->map(fn (PurchaseOrderTransaction $line) => [
                'id'                       => $line->id,
                'purchase_order_id'        => $line->purchase_order_id,
                'purchase_order_slug'      => $line->purchase_order_slug,
                'purchase_order_reference' => $line->purchase_order_reference,
                'purchase_order_state'     => PurchaseOrderStateEnum::labels()[$line->purchase_order_state] ?? $line->purchase_order_state,
                'purchase_order_date'      => $line->purchase_order_date,
                'is_current'               => $line->purchase_order_id === $stock->purchase_order_id,
                'quantity_ordered'         => (float) $line->quantity_ordered,
                'quantity_at_agent'        => (float) $line->quantity_at_agent,
                'has_goods_receipt'        => (bool) $line->has_goods_receipt,
                'deposit_amount'           => $line->deposit_amount !== null ? (float) $line->deposit_amount : null,
                'deposit_paid_at'          => $line->deposit_paid_at,
                'currency_code'            => $line->currency_code,
            ])
            ->all();
    }

    public function asController(Organisation $organisation, AgentWarehouseStock $agentWarehouseStock, ActionRequest $request): array
    {
        abort_unless($organisation->agent && $agentWarehouseStock->agent_id === $organisation->agent->id, 404);
        $this->initialisation($organisation, $request);

        return $this->handle($agentWarehouseStock);
    }
}
