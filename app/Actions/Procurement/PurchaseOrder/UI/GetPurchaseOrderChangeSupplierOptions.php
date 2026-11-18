<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-17 15:01
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\PurchaseOrder\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\ChangePurchaseOrderSupplier;
use App\Actions\Procurement\PurchaseOrderTransaction\GetPurchaseOrderTransactionMoveSuppliers;
use App\Actions\Traits\Authorisations\WithPurchaseOrderAgentEditAuthorisation;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

/**
 * What the Change supplier dialog needs: the order's lines with their agreed price, and the other suppliers of the agent
 * in the order's currency, each with the reference the order would take, its orders the lines can join (same agent order
 * first) and its product for the SKO of every line, plus the money on the order that goes with it when it joins one.
 */
class GetPurchaseOrderChangeSupplierOptions extends OrgAction
{
    use WithPurchaseOrderAgentEditAuthorisation;

    public function handle(PurchaseOrder $purchaseOrder): array
    {
        $currency = $purchaseOrder->currency?->code;
        $lines    = ChangePurchaseOrderSupplier::linesToChange($purchaseOrder);

        $suppliers = collect(GetPurchaseOrderTransactionMoveSuppliers::make()->handle($purchaseOrder, $lines))
            ->reject(fn (array $supplier) => $supplier['is_current'] || $supplier['currency'] !== $currency);

        $ordersToJoinBySupplier = ChangePurchaseOrderSupplier::purchaseOrdersToJoinQuery($purchaseOrder, $suppliers->pluck('org_supplier_id')->all())
            ->orderByRaw('agent_order_reference is not distinct from ? desc', [$purchaseOrder->agent_order_reference])
            ->orderByDesc('id')
            ->get(['id', 'parent_id', 'reference', 'slug', 'state', 'agent_order_reference', 'number_purchase_order_transactions'])
            ->groupBy('parent_id');

        $suppliers = $suppliers
            ->map(fn (array $supplier) => [
                'org_supplier_id' => $supplier['org_supplier_id'],
                'code'            => $supplier['code'],
                'name'            => $supplier['name'],
                'reference'       => ChangePurchaseOrderSupplier::referenceFor($purchaseOrder, $supplier['code']),
                'products'        => $supplier['products'],
                'products_count'  => $supplier['products_count'],
                'purchase_orders' => $ordersToJoinBySupplier->get($supplier['org_supplier_id'], collect())->map(fn (PurchaseOrder $order) => [
                    'id'                  => $order->id,
                    'reference'           => $order->reference,
                    'slug'                => $order->slug,
                    'state_label'         => PurchaseOrderStateEnum::labels()[$order->state->value] ?? $order->state->value,
                    'number_of_items'     => (int) $order->number_purchase_order_transactions,
                    'is_same_agent_order' => $purchaseOrder->agent_order_reference !== null && $order->agent_order_reference === $purchaseOrder->agent_order_reference,
                ])->values()->all(),
            ])
            ->values()
            ->all();

        return [
            'blocked_reason' => ChangePurchaseOrderSupplier::blockedReason($purchaseOrder),
            'reference'      => $purchaseOrder->reference,
            'slug'           => $purchaseOrder->slug,
            'supplier'       => [
                'code' => $purchaseOrder->parent_code,
                'name' => $purchaseOrder->parent_name,
            ],
            'currency'       => $currency,
            'money'          => [
                'deposit_payments' => (float) $purchaseOrder->depositPayments()->sum('amount'),
                'agent_payments'   => (float) $purchaseOrder->agentSupplierPayments()->sum('amount'),
                'money_requested'  => DB::table('deposit_request_items')->where('purchase_order_id', $purchaseOrder->id)->exists(),
                'deposit_paid_at'  => $purchaseOrder->deposit_paid_at?->toDateString(),
            ],
            'lines'          => $lines->map(fn (PurchaseOrderTransaction $transaction) => [
                'id'               => $transaction->id,
                'code'             => $transaction->supplierProduct?->code ?? $transaction->orgStock?->code,
                'name'             => $transaction->supplierProduct?->name ?? $transaction->orgStock?->name,
                'has_sko'          => $transaction->org_stock_id !== null,
                'quantity_ordered' => (float) $transaction->quantity_ordered,
                'unit_cost'        => ChangePurchaseOrderSupplier::agreedUnitCost($transaction),
                'units_per_carton' => $transaction->supplierProduct?->units_per_carton !== null ? (float) $transaction->supplierProduct->units_per_carton : null,
            ])->values()->all(),
            'suppliers'      => $suppliers,
        ];
    }

    public function asController(PurchaseOrder $purchaseOrder, ActionRequest $request): array
    {
        $this->initialisation($purchaseOrder->organisation, $request);

        return $this->handle($purchaseOrder);
    }
}
