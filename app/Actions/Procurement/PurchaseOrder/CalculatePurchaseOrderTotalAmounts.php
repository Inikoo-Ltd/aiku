<?php

/*
 * author Arya Permana - Kirin
 * created on 14-11-2024-13h-17m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Helpers\CurrencyExchange\GetHistoricCurrencyExchange;
use App\Actions\OrgAction;
use App\Actions\SupplyChain\AgentSupplierPurchaseOrder\StoreAgentSupplierPurchaseOrdersFromPurchaseOrder;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use RuntimeException;

class CalculatePurchaseOrderTotalAmounts extends OrgAction
{
    public function handle(PurchaseOrder $purchaseOrder): void
    {
        $itemsNet = $purchaseOrder->purchaseOrderTransactions()
            ->with('supplierProduct.currency')
            ->get()
            ->sum(fn (PurchaseOrderTransaction $transaction) => $this->netAmountInOrderCurrency($purchaseOrder, $transaction));

        $extras = (float) $purchaseOrder->cost_extra
            + (float) $purchaseOrder->cost_shipping
            + (float) $purchaseOrder->cost_duties
            + (float) $purchaseOrder->cost_tax;

        $purchaseOrder->update([
            'cost_items' => $itemsNet,
            'cost_total' => $itemsNet + $extras,
        ]);

        if ($purchaseOrder->parent instanceof OrgAgent && $purchaseOrder->state != PurchaseOrderStateEnum::IN_PROCESS) {
            StoreAgentSupplierPurchaseOrdersFromPurchaseOrder::make()->action($purchaseOrder);
        }
    }

    private function netAmountInOrderCurrency(PurchaseOrder $purchaseOrder, PurchaseOrderTransaction $transaction): float
    {
        $netAmount       = (float) $transaction->net_amount;
        $supplierProduct = $transaction->supplierProduct;

        if (!$supplierProduct || $supplierProduct->currency_id === $purchaseOrder->currency_id) {
            return $netAmount;
        }

        $rate = GetHistoricCurrencyExchange::run($supplierProduct->currency, $purchaseOrder->currency, $purchaseOrder->date)
            ?? GetCurrencyExchange::run($supplierProduct->currency, $purchaseOrder->currency);

        if ($rate === null) {
            throw new RuntimeException(
                "No exchange rate found from {$supplierProduct->currency->code} to {$purchaseOrder->currency->code} for purchase order {$purchaseOrder->reference}"
            );
        }

        return $netAmount * $rate;
    }
}
