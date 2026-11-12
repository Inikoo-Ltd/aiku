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
use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateState;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use RuntimeException;

class CalculatePurchaseOrderTotalAmounts extends OrgAction
{
    /**
     * A purchase order carries only what is ordered from the supplier, so its total is its items. Freight, duty
     * and every other landed cost live on the stock delivery, where they are costed.
     */
    public function handle(PurchaseOrder $purchaseOrder): void
    {
        $itemsNet = $this->itemsNet($purchaseOrder);

        $purchaseOrder->update([
            'cost_items' => $itemsNet,
            'cost_total' => $itemsNet,
        ]);

        if ($purchaseOrder->wasChanged('cost_items') && $purchaseOrder->depositPayments()->exists()) {
            SyncPurchaseOrderDepositPaid::run($purchaseOrder);
        }

        PurchaseOrderHydrateState::run($purchaseOrder);
    }

    public function itemsNet(PurchaseOrder $purchaseOrder): float
    {
        return (float) $purchaseOrder->purchaseOrderTransactions()
            ->with('supplierProduct.currency')
            ->get()
            ->sum(fn (PurchaseOrderTransaction $transaction) => $this->netAmountInOrderCurrency($purchaseOrder, $transaction));
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
