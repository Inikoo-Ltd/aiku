<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\DepositRequest;

use App\Actions\SupplyChain\AgentPayment\UI\IndexAgentLedger;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\SupplyChain\AgentPayment\AgentPaymentTypeEnum;
use App\Enums\SupplyChain\AspoDeposit\DepositRequestItemKindEnum;
use App\Enums\SupplyChain\AspoDeposit\DepositRequestStateEnum;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * What an agent can still ask a client for: on each open supplier order the advance (the supplier's advance %,
 * 30 when not set) and the rest of the order, less what is already covered, the charges the agent paid for each
 * shipment, and whatever brings the client's wallet back to zero. An order is covered by what was asked for it
 * or, when more, by what the ledger shows paid to its supplier: money paid out that was never asked for is already
 * in the negative wallet, which its own line asks for, so the order does not ask for it again. Shipment charges
 * are the other way round: each shipment asks for its own, less what was asked for it or sent against it, so the
 * wallet line leaves out what the shipment lines ask for or an open request already asked for.
 * Amounts are in the agent's currency; orders in another currency are left out.
 */
class GetAgentMoneyRequestLines
{
    use AsObject;

    public const float DEFAULT_ADVANCE_PERCENTAGE = 30;

    /**
     * @return array{currency_code: string, wallet: array{received: float, paid: float, balance: float}, negative_balance: float, shipments: list<array{stock_delivery_id: int, stock_delivery_slug: string, reference: string, state: string|null, charges: list<array{type: string, label: string, amount: float}>, total: float, requested: float, due: float}>, orders: list<array{purchase_order_id: int, purchase_order_slug: string, reference: string, submitted_at: string|null, placed_by: string|null, supplier_id: int|null, supplier_slug: string|null, supplier_code: string|null, supplier_name: string|null, supplier_company: string|null, supplier_nickname: string|null, total: float, requested: float, advance_percentage: float, advance_due: float, balance_due: float}>}
     */
    public function handle(Agent $agent, Organisation $client, ?int $purchaseOrderId = null): array
    {
        $currency = $agent->organisation->currency;
        $wallet   = collect(IndexAgentLedger::make()->wallets($agent))
            ->first(fn (array $wallet) => $wallet['organisation_id'] === $client->id && $wallet['currency_code'] === $currency->code)
            ?? ['received' => 0.0, 'paid' => 0.0, 'balance' => 0.0];

        $requested = DB::table('deposit_request_items')
            ->join('deposit_requests', 'deposit_requests.id', 'deposit_request_items.deposit_request_id')
            ->whereNotIn('deposit_requests.state', [DepositRequestStateEnum::CANCELLED->value, DepositRequestStateEnum::REJECTED->value])
            ->whereNotNull('deposit_request_items.purchase_order_id')
            ->groupBy('deposit_request_items.purchase_order_id')
            ->selectRaw('deposit_request_items.purchase_order_id, sum(deposit_request_items.amount) as amount');

        $paidToSuppliers = DB::table('agent_payments')
            ->where('agent_payments.agent_id', $agent->id)
            ->where('agent_payments.organisation_id', $client->id)
            ->whereIn('agent_payments.type', array_map(fn (AgentPaymentTypeEnum $type) => $type->value, array_values(array_filter(AgentPaymentTypeEnum::cases(), fn (AgentPaymentTypeEnum $type) => $type->isSupplierPayment()))))
            ->whereNotNull('agent_payments.purchase_order_id')
            ->whereNull('agent_payments.deleted_at')
            ->groupBy('agent_payments.purchase_order_id')
            ->selectRaw('agent_payments.purchase_order_id, sum(agent_payments.amount) as amount');

        $orders = PurchaseOrder::query()
            ->where('purchase_orders.organisation_id', $client->id)
            ->where('purchase_orders.agent_id', $agent->id)
            ->where('purchase_orders.currency_id', $currency->id)
            ->trackedThroughAgent()
            ->whereIn('purchase_orders.state', [PurchaseOrderStateEnum::SUBMITTED, PurchaseOrderStateEnum::CONFIRMED])
            ->when($purchaseOrderId, fn ($query) => $query->where('purchase_orders.id', $purchaseOrderId))
            ->leftJoin('suppliers', 'suppliers.id', 'purchase_orders.supplier_id')
            ->leftJoin('users as buyers', 'buyers.id', 'purchase_orders.buyer_id')
            ->leftJoinSub($requested, 'requested', 'requested.purchase_order_id', 'purchase_orders.id')
            ->leftJoinSub($paidToSuppliers, 'paid_to_suppliers', 'paid_to_suppliers.purchase_order_id', 'purchase_orders.id')
            ->orderBy('purchase_orders.reference')
            ->orderBy('suppliers.code')
            ->get([
                'purchase_orders.id',
                'purchase_orders.slug',
                'purchase_orders.reference',
                'purchase_orders.submitted_at',
                'purchase_orders.supplier_id',
                'purchase_orders.cost_items',
                'suppliers.slug as supplier_slug',
                'suppliers.code as supplier_code',
                'suppliers.name as supplier_name',
                'suppliers.company_name as supplier_company_name',
                'buyers.contact_name as buyer_name',
                'buyers.username as buyer_username',
                'suppliers.advance_percentage',
                'requested.amount as requested',
                'paid_to_suppliers.amount as paid_to_supplier',
            ])
            ->map(function (PurchaseOrder $purchaseOrder) {
                $total      = round((float) $purchaseOrder->cost_items, 2);
                $requested  = round((float) $purchaseOrder->requested, 2);
                $paid       = round((float) $purchaseOrder->paid_to_supplier, 2);
                $covered    = max($requested, $paid);
                $percentage = $purchaseOrder->advance_percentage !== null ? (float) $purchaseOrder->advance_percentage : self::DEFAULT_ADVANCE_PERCENTAGE;
                $advanceDue = max(0, round($total * $percentage / 100 - $covered, 2));
                $company    = $purchaseOrder->supplier_company_name ?: $purchaseOrder->supplier_name;

                return [
                    'purchase_order_id'     => $purchaseOrder->id,
                    'purchase_order_slug'   => $purchaseOrder->slug,
                    'reference'             => $purchaseOrder->reference,
                    'submitted_at'          => $purchaseOrder->submitted_at?->toDateString(),
                    'placed_by'             => $purchaseOrder->buyer_name ?: $purchaseOrder->buyer_username,
                    'supplier_id'           => $purchaseOrder->supplier_id,
                    'supplier_slug'         => $purchaseOrder->supplier_slug,
                    'supplier_code'         => $purchaseOrder->supplier_code,
                    'supplier_name'         => $purchaseOrder->supplier_name,
                    'supplier_company'      => $company,
                    'supplier_nickname'     => $purchaseOrder->supplier_name && $purchaseOrder->supplier_name !== $company ? $purchaseOrder->supplier_name : null,
                    'total'                 => $total,
                    'requested'             => $requested,
                    'paid_to_supplier'      => $paid,
                    'covered'               => $covered,
                    'advance_percentage'    => $percentage,
                    'advance_due'           => $advanceDue,
                    'balance_due'           => max(0, round($total - $covered - $advanceDue, 2)),
                ];
            })
            ->filter(fn (array $order) => $order['advance_due'] > 0 || $order['balance_due'] > 0)
            ->values()
            ->all();

        $walletAlreadyRequested = (float) DB::table('deposit_request_items')
            ->join('deposit_requests', 'deposit_requests.id', 'deposit_request_items.deposit_request_id')
            ->where('deposit_requests.agent_id', $agent->id)
            ->where('deposit_requests.organisation_id', $client->id)
            ->where('deposit_requests.state', DepositRequestStateEnum::REQUESTED->value)
            ->where('deposit_request_items.kind', DepositRequestItemKindEnum::WALLET->value)
            ->sum('deposit_request_items.amount');

        $shipments                    = $this->shipments($agent, $client, $currency->id);
        $shipmentsAskedInOpenRequests = (float) DB::table('deposit_request_items')
            ->join('deposit_requests', 'deposit_requests.id', 'deposit_request_items.deposit_request_id')
            ->where('deposit_requests.agent_id', $agent->id)
            ->where('deposit_requests.organisation_id', $client->id)
            ->where('deposit_requests.state', DepositRequestStateEnum::REQUESTED->value)
            ->where('deposit_request_items.kind', DepositRequestItemKindEnum::SHIPMENT->value)
            ->sum('deposit_request_items.amount');
        $shipmentsStillDue            = array_sum(array_column($shipments, 'due'));

        return [
            'currency_code'    => $currency->code,
            'wallet'           => ['received' => $wallet['received'], 'paid' => $wallet['paid'], 'balance' => $wallet['balance']],
            'negative_balance' => max(0, round(-$wallet['balance'] - $walletAlreadyRequested - $shipmentsStillDue - $shipmentsAskedInOpenRequests, 2)),
            'shipments'        => $purchaseOrderId ? [] : array_values(array_filter($shipments, fn (array $shipment) => $shipment['due'] > 0)),
            'orders'           => $orders,
        ];
    }

    /**
     * Every shipment the agent paid charges for on the client's behalf, with what is still to ask for: the charges
     * less what was asked for the shipment in a request still standing or, when more, sent against it directly.
     *
     * @return list<array{stock_delivery_id: int, stock_delivery_slug: string, reference: string, state: string|null, charges: list<array{type: string, label: string, amount: float}>, total: float, requested: float, due: float}>
     */
    private function shipments(Agent $agent, Organisation $client, int $currencyId): array
    {
        $chargeTypes = array_values(array_map(fn (AgentPaymentTypeEnum $type) => $type->value, array_filter(AgentPaymentTypeEnum::cases(), fn (AgentPaymentTypeEnum $type) => $type->isCharge())));

        $charges = DB::table('agent_payments')
            ->join('stock_deliveries', 'stock_deliveries.id', 'agent_payments.stock_delivery_id')
            ->where('agent_payments.agent_id', $agent->id)
            ->where('agent_payments.organisation_id', $client->id)
            ->where('agent_payments.currency_id', $currencyId)
            ->whereIn('agent_payments.type', $chargeTypes)
            ->whereNull('agent_payments.deleted_at')
            ->groupBy('agent_payments.stock_delivery_id', 'stock_deliveries.slug', 'stock_deliveries.reference', 'stock_deliveries.state', 'agent_payments.type')
            ->orderBy('stock_deliveries.reference')
            ->get([
                'agent_payments.stock_delivery_id',
                'stock_deliveries.slug',
                'stock_deliveries.reference',
                'stock_deliveries.state',
                'agent_payments.type',
                DB::raw('sum(agent_payments.amount) as amount'),
            ]);

        if ($charges->isEmpty()) {
            return [];
        }

        $stockDeliveryIds = $charges->pluck('stock_delivery_id')->unique()->values()->all();

        $requested = DB::table('deposit_request_items')
            ->join('deposit_requests', 'deposit_requests.id', 'deposit_request_items.deposit_request_id')
            ->whereNotIn('deposit_requests.state', [DepositRequestStateEnum::CANCELLED->value, DepositRequestStateEnum::REJECTED->value])
            ->where('deposit_request_items.kind', DepositRequestItemKindEnum::SHIPMENT->value)
            ->whereIn('deposit_request_items.stock_delivery_id', $stockDeliveryIds)
            ->groupBy('deposit_request_items.stock_delivery_id')
            ->pluck(DB::raw('sum(deposit_request_items.amount)'), 'deposit_request_items.stock_delivery_id');

        $sentAgainstShipment = DB::table('agent_payments')
            ->where('agent_payments.agent_id', $agent->id)
            ->where('agent_payments.organisation_id', $client->id)
            ->where('agent_payments.type', AgentPaymentTypeEnum::TRANSFER_RECEIVED->value)
            ->whereIn('agent_payments.stock_delivery_id', $stockDeliveryIds)
            ->whereNull('agent_payments.deleted_at')
            ->groupBy('agent_payments.stock_delivery_id')
            ->pluck(DB::raw('sum(agent_payments.amount)'), 'agent_payments.stock_delivery_id');

        $labels = AgentPaymentTypeEnum::labels();

        return $charges->groupBy('stock_delivery_id')->map(function ($rows, $stockDeliveryId) use ($requested, $sentAgainstShipment, $labels) {
            $first         = $rows->first();
            $total         = round((float) $rows->sum('amount'), 2);
            $requestedHere = round((float) ($requested[$stockDeliveryId] ?? 0), 2);
            $covered       = max($requestedHere, round((float) ($sentAgainstShipment[$stockDeliveryId] ?? 0), 2));

            return [
                'stock_delivery_id'   => (int) $stockDeliveryId,
                'stock_delivery_slug' => $first->slug,
                'reference'           => $first->reference,
                'state'               => $first->state,
                'charges'             => $rows->map(fn ($row) => [
                    'type'   => $row->type,
                    'label'  => $labels[$row->type] ?? $row->type,
                    'amount' => round((float) $row->amount, 2),
                ])->sortByDesc('amount')->values()->all(),
                'total'               => $total,
                'requested'           => $requestedHere,
                'due'                 => max(0, round($total - $covered, 2)),
            ];
        })->values()->all();
    }
}
