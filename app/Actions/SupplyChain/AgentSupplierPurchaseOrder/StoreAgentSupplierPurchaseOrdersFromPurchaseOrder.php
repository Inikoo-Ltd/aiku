<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 20:30:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\AgentSupplierPurchaseOrder;

use App\Actions\OrgAction;
use App\Enums\SupplyChain\AgentSupplierPurchaseOrders\AgentSupplierPurchaseOrderStateEnum;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\AgentSupplierPurchaseOrder;
use App\Models\SupplyChain\Supplier;
use Illuminate\Support\Collection;

class StoreAgentSupplierPurchaseOrdersFromPurchaseOrder extends OrgAction
{
    /**
     * The agent internally buys from each of its suppliers: split the organisation's
     * purchase order into one AgentSupplierPurchaseOrder per supplier and link the lines.
     *
     * @return Collection<int, AgentSupplierPurchaseOrder>
     */
    public function handle(PurchaseOrder $purchaseOrder): Collection
    {
        $agentSupplierPurchaseOrders = collect();

        if (!$purchaseOrder->parent instanceof OrgAgent) {
            return $agentSupplierPurchaseOrders;
        }

        $transactionsBySupplier = $purchaseOrder->purchaseOrderTransactions()
            ->join('supplier_products', 'supplier_products.id', 'purchase_order_transactions.supplier_product_id')
            ->selectRaw("purchase_order_transactions.id, purchase_order_transactions.net_amount, supplier_products.supplier_id, (supplier_products.data->>'delivery_time')::int as delivery_time")
            ->get()
            ->groupBy('supplier_id');

        foreach ($transactionsBySupplier as $supplierId => $transactions) {
            $supplier = Supplier::find($supplierId);
            if (!$supplier) {
                continue;
            }

            $agentSupplierPurchaseOrder = AgentSupplierPurchaseOrder::where('purchase_order_id', $purchaseOrder->id)
                ->where('supplier_id', $supplier->id)
                ->first();

            if (!$agentSupplierPurchaseOrder) {
                $agentSupplierPurchaseOrder = StoreAgentSupplierPurchaseOrder::make()->action(
                    purchaseOrder: $purchaseOrder,
                    supplier: $supplier,
                    modelData: []
                );
            }

            $purchaseOrder->purchaseOrderTransactions()
                ->whereIn('purchase_order_transactions.id', $transactions->pluck('id'))
                ->update(['agent_supplier_purchase_order_id' => $agentSupplierPurchaseOrder->id]);

            $updateData = [
                'state'        => AgentSupplierPurchaseOrderStateEnum::SUBMITTED,
                'cost_items'   => $transactions->sum('net_amount'),
                'cost_total'   => $transactions->sum('net_amount'),
                'date'         => now(),
                'submitted_at' => now(),
            ];

            if ($agentSupplierPurchaseOrder->estimated_delivery_days === null) {
                $deliveryDays = $transactions->max('delivery_time');

                if ($deliveryDays !== null) {
                    $updateData['estimated_delivery_days'] = $deliveryDays;
                    $updateData['estimated_received_at']   = now()->addDays($deliveryDays);
                }
            }

            UpdateAgentSupplierPurchaseOrder::make()->action(
                $agentSupplierPurchaseOrder,
                $updateData,
                strict: false
            );

            $agentSupplierPurchaseOrders->push($agentSupplierPurchaseOrder->refresh());
        }

        return $agentSupplierPurchaseOrders;
    }

    public function action(PurchaseOrder $purchaseOrder): Collection
    {
        $this->asAction = true;
        $this->initialisationFromGroup($purchaseOrder->group, []);

        return $this->handle($purchaseOrder);
    }
}
