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
 * What an agent can still ask a client for, every amount on its own line: on each open supplier order the advance
 * (the supplier's advance %, 30 when not set) and the rest of the order less what was already asked for it, on an
 * order no longer open what the agent paid its supplier beyond what was asked, the charges the agent paid for each
 * shipment, and each expense of the ledger that belongs to no shipment (supplier payments without an order among
 * them). The client's money the agent holds and was never asked for (transfers outside a request, supplier
 * refunds) is the credit, which the request takes off its lines. Wallet lines of older requests asked for these
 * costs at once, so what they asked counts as credit too. Amounts are in the agent's currency; open orders in
 * another currency are left out.
 */
class GetAgentMoneyRequestLines
{
    use AsObject;

    public const float DEFAULT_ADVANCE_PERCENTAGE = 30;

    /**
     * @return array{currency_code: string, wallet: array{received: float, paid: float, balance: float}, credit_available: float, shipments: list<array{stock_delivery_id: int, stock_delivery_slug: string, reference: string, state: string|null, charges: list<array{type: string, label: string, amount: float}>, total: float, requested: float, due: float}>, expenses: list<array{agent_payment_id: int, type: string, type_label: string, date: string, amount: float, requested: float, due: float, reference: string|null, notes: string|null, supplier_code: string|null, supplier_name: string|null, purchase_order_reference: string|null, purchase_order_slug: string|null, receipt: string|null}>, orders: list<array{purchase_order_id: int, purchase_order_slug: string, reference: string, is_open: bool, submitted_at: string|null, placed_by: string|null, supplier_id: int|null, supplier_slug: string|null, supplier_code: string|null, supplier_name: string|null, supplier_company: string|null, supplier_nickname: string|null, total: float, requested: float, paid_to_supplier: float, advance_percentage: float, advance_due: float, balance_due: float}>}
     */
    public function handle(Agent $agent, Organisation $client, ?int $purchaseOrderId = null): array
    {
        $currency = $agent->organisation->currency;
        $wallet   = collect(IndexAgentLedger::make()->wallets($agent))
            ->first(fn (array $wallet) => $wallet['organisation_id'] === $client->id && $wallet['currency_code'] === $currency->code)
            ?? ['received' => 0.0, 'paid' => 0.0, 'balance' => 0.0];

        $shipments = $purchaseOrderId ? [] : $this->shipments($agent, $client, $currency->id);

        return [
            'currency_code'    => $currency->code,
            'wallet'           => ['received' => $wallet['received'], 'paid' => $wallet['paid'], 'balance' => $wallet['balance']],
            'credit_available' => self::creditAvailable($agent, $client),
            'shipments'        => array_values(array_filter($shipments, fn (array $shipment) => $shipment['due'] > 0)),
            'expenses'         => $purchaseOrderId ? [] : $this->expenses($agent, $client, $currency->id),
            'orders'           => $this->orders($agent, $client, $currency->id, $purchaseOrderId),
        ];
    }

    /**
     * The client's money the agent holds and no request asked for yet: transfers sent outside a request and not
     * against a shipment, and refunds from suppliers, plus what wallet lines of older requests asked for, less what
     * credit lines of standing requests already took off.
     */
    public static function creditAvailable(Agent $agent, Organisation $client): float
    {
        $freeMoneyIn = (float) DB::table('agent_payments')
            ->where('agent_payments.agent_id', $agent->id)
            ->where('agent_payments.organisation_id', $client->id)
            ->where('agent_payments.currency_id', $agent->organisation->currency_id)
            ->whereIn('agent_payments.type', AgentPaymentTypeEnum::moneyInValues())
            ->whereNull('agent_payments.deposit_request_id')
            ->where(fn ($query) => $query
                ->where('agent_payments.type', '!=', AgentPaymentTypeEnum::TRANSFER_RECEIVED->value)
                ->orWhereNull('agent_payments.stock_delivery_id'))
            ->whereNull('agent_payments.deleted_at')
            ->sum('agent_payments.amount');

        $askedOrTakenOff = (float) DB::table('deposit_request_items')
            ->join('deposit_requests', 'deposit_requests.id', 'deposit_request_items.deposit_request_id')
            ->where('deposit_requests.agent_id', $agent->id)
            ->where('deposit_requests.organisation_id', $client->id)
            ->whereNotIn('deposit_requests.state', [DepositRequestStateEnum::CANCELLED->value, DepositRequestStateEnum::REJECTED->value])
            ->whereIn('deposit_request_items.kind', [DepositRequestItemKindEnum::WALLET->value, DepositRequestItemKindEnum::CREDIT->value])
            ->sum('deposit_request_items.amount');

        return max(0, round($freeMoneyIn + $askedOrTakenOff, 2));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function orders(Agent $agent, Organisation $client, int $currencyId, ?int $purchaseOrderId): array
    {
        $openStates = PurchaseOrderStateEnum::openValues();

        $requested = DB::table('deposit_request_items')
            ->join('deposit_requests', 'deposit_requests.id', 'deposit_request_items.deposit_request_id')
            ->whereNotIn('deposit_requests.state', [DepositRequestStateEnum::CANCELLED->value, DepositRequestStateEnum::REJECTED->value])
            ->whereNotNull('deposit_request_items.purchase_order_id')
            ->groupBy('deposit_request_items.purchase_order_id')
            ->selectRaw('deposit_request_items.purchase_order_id, sum(deposit_request_items.amount) as amount');

        $paidToSuppliers = DB::table('agent_payments')
            ->where('agent_payments.agent_id', $agent->id)
            ->where('agent_payments.organisation_id', $client->id)
            ->where('agent_payments.currency_id', $currencyId)
            ->whereIn('agent_payments.type', AgentPaymentTypeEnum::supplierPaymentValues())
            ->whereNotNull('agent_payments.purchase_order_id')
            ->whereNull('agent_payments.deleted_at')
            ->groupBy('agent_payments.purchase_order_id')
            ->selectRaw('agent_payments.purchase_order_id, sum(agent_payments.amount) as amount');

        return PurchaseOrder::query()
            ->where('purchase_orders.organisation_id', $client->id)
            ->where('purchase_orders.agent_id', $agent->id)
            ->trackedThroughAgent()
            ->when($purchaseOrderId, fn ($query) => $query->where('purchase_orders.id', $purchaseOrderId))
            ->leftJoin('suppliers', 'suppliers.id', 'purchase_orders.supplier_id')
            ->leftJoin('users as buyers', 'buyers.id', 'purchase_orders.buyer_id')
            ->leftJoinSub($requested, 'requested', 'requested.purchase_order_id', 'purchase_orders.id')
            ->leftJoinSub($paidToSuppliers, 'paid_to_suppliers', 'paid_to_suppliers.purchase_order_id', 'purchase_orders.id')
            ->where(fn ($query) => $query
                ->where(fn ($open) => $open->whereIn('purchase_orders.state', $openStates)->where('purchase_orders.currency_id', $currencyId))
                ->orWhereRaw('coalesce(paid_to_suppliers.amount, 0) > coalesce(requested.amount, 0) + 0.005'))
            ->orderBy('purchase_orders.reference')
            ->orderBy('suppliers.code')
            ->get([
                'purchase_orders.id',
                'purchase_orders.slug',
                'purchase_orders.reference',
                'purchase_orders.state',
                'purchase_orders.currency_id',
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
            ->map(function (PurchaseOrder $purchaseOrder) use ($openStates, $currencyId) {
                $isOpen     = in_array($purchaseOrder->state->value, $openStates, true) && (int) $purchaseOrder->currency_id === $currencyId;
                $paid       = round((float) $purchaseOrder->paid_to_supplier, 2);
                $total      = $isOpen ? round((float) $purchaseOrder->cost_items, 2) : $paid;
                $requested  = round((float) $purchaseOrder->requested, 2);
                $percentage = $purchaseOrder->advance_percentage !== null ? (float) $purchaseOrder->advance_percentage : self::DEFAULT_ADVANCE_PERCENTAGE;
                $toCover    = max($total, $paid);
                $advanceDue = $isOpen ? max(0, round($toCover * $percentage / 100 - $requested, 2)) : 0;
                $company    = $purchaseOrder->supplier_company_name ?: $purchaseOrder->supplier_name;

                return [
                    'purchase_order_id'     => $purchaseOrder->id,
                    'purchase_order_slug'   => $purchaseOrder->slug,
                    'reference'             => $purchaseOrder->reference,
                    'is_open'               => $isOpen,
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
                    'advance_percentage'    => $percentage,
                    'advance_due'           => $advanceDue,
                    'balance_due'           => max(0, round($toCover - $requested - $advanceDue, 2)),
                ];
            })
            ->filter(fn (array $order) => $order['advance_due'] > 0 || $order['balance_due'] > 0)
            ->values()
            ->all();
    }

    /**
     * Each ledger expense that belongs to no shipment, and each supplier payment that belongs to no order, with what
     * is still to ask for it, newest first.
     *
     * @return list<array<string, mixed>>
     */
    private function expenses(Agent $agent, Organisation $client, int $currencyId): array
    {
        $chargeTypes = array_values(array_map(fn (AgentPaymentTypeEnum $type) => $type->value, array_filter(AgentPaymentTypeEnum::cases(), fn (AgentPaymentTypeEnum $type) => $type->isCharge())));

        $requested = DB::table('deposit_request_items')
            ->join('deposit_requests', 'deposit_requests.id', 'deposit_request_items.deposit_request_id')
            ->whereNotIn('deposit_requests.state', [DepositRequestStateEnum::CANCELLED->value, DepositRequestStateEnum::REJECTED->value])
            ->where('deposit_request_items.kind', DepositRequestItemKindEnum::EXPENSE->value)
            ->whereNotNull('deposit_request_items.agent_payment_id')
            ->groupBy('deposit_request_items.agent_payment_id')
            ->selectRaw('deposit_request_items.agent_payment_id, sum(deposit_request_items.amount) as amount');

        $labels = AgentPaymentTypeEnum::labels();

        return DB::table('agent_payments')
            ->leftJoin('suppliers', 'suppliers.id', 'agent_payments.supplier_id')
            ->leftJoin('purchase_orders', 'purchase_orders.id', 'agent_payments.purchase_order_id')
            ->leftJoin('media', 'media.id', 'agent_payments.media_id')
            ->leftJoinSub($requested, 'requested', 'requested.agent_payment_id', 'agent_payments.id')
            ->where('agent_payments.agent_id', $agent->id)
            ->where('agent_payments.organisation_id', $client->id)
            ->where('agent_payments.currency_id', $currencyId)
            ->whereNull('agent_payments.deleted_at')
            ->where(fn ($query) => $query
                ->where(fn ($charges) => $charges->whereIn('agent_payments.type', $chargeTypes)->whereNull('agent_payments.stock_delivery_id'))
                ->orWhere(fn ($payments) => $payments->whereIn('agent_payments.type', AgentPaymentTypeEnum::supplierPaymentValues())->whereNull('agent_payments.purchase_order_id')))
            ->whereRaw('agent_payments.amount > coalesce(requested.amount, 0) + 0.005')
            ->orderByDesc('agent_payments.date')
            ->orderByDesc('agent_payments.id')
            ->get([
                'agent_payments.id',
                'agent_payments.type',
                'agent_payments.date',
                'agent_payments.amount',
                'agent_payments.reference',
                'agent_payments.notes',
                'media.ulid as receipt',
                'suppliers.code as supplier_code',
                'suppliers.name as supplier_name',
                'purchase_orders.reference as purchase_order_reference',
                'purchase_orders.slug as purchase_order_slug',
                'requested.amount as requested',
            ])
            ->map(function ($entry) use ($labels) {
                $amount    = round((float) $entry->amount, 2);
                $requested = round((float) $entry->requested, 2);

                return [
                    'agent_payment_id'         => $entry->id,
                    'type'                     => $entry->type,
                    'type_label'               => $labels[$entry->type] ?? $entry->type,
                    'date'                     => $entry->date ? substr((string) $entry->date, 0, 10) : null,
                    'amount'                   => $amount,
                    'requested'                => $requested,
                    'due'                      => max(0, round($amount - $requested, 2)),
                    'reference'                => $entry->reference,
                    'notes'                    => $entry->notes,
                    'supplier_code'            => $entry->supplier_code,
                    'supplier_name'            => $entry->supplier_name,
                    'purchase_order_reference' => $entry->purchase_order_reference,
                    'purchase_order_slug'      => $entry->purchase_order_slug,
                    'receipt'                  => $entry->receipt,
                ];
            })
            ->values()
            ->all();
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
            ->selectRaw('deposit_request_items.stock_delivery_id, sum(deposit_request_items.amount) as total_amount')
            ->pluck('total_amount', 'stock_delivery_id');

        $sentAgainstShipment = DB::table('agent_payments')
            ->where('agent_payments.agent_id', $agent->id)
            ->where('agent_payments.organisation_id', $client->id)
            ->where('agent_payments.type', AgentPaymentTypeEnum::TRANSFER_RECEIVED->value)
            ->whereIn('agent_payments.stock_delivery_id', $stockDeliveryIds)
            ->whereNull('agent_payments.deleted_at')
            ->groupBy('agent_payments.stock_delivery_id')
            ->selectRaw('agent_payments.stock_delivery_id, sum(agent_payments.amount) as total_amount')
            ->pluck('total_amount', 'stock_delivery_id');

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
