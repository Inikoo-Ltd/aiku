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
            ->with('currency')
            ->get()
            ->sum(fn (PurchaseOrderTransaction $transaction) => $this->netAmountInOrderCurrency($purchaseOrder, $transaction));
    }

    /**
     * A line keeps the currency its amount is in, which is not always the order's: a line made in aiku is priced from
     * its supplier product. A line without one is in the order's currency.
     */
    private function netAmountInOrderCurrency(PurchaseOrder $purchaseOrder, PurchaseOrderTransaction $transaction): float
    {
        $netAmount = (float) $transaction->net_amount;
        $currency  = $transaction->currency;

        if (!$currency || $currency->id === $purchaseOrder->currency_id) {
            return $netAmount;
        }

        $rate = GetHistoricCurrencyExchange::run($currency, $purchaseOrder->currency, $purchaseOrder->date)
            ?? GetCurrencyExchange::run($currency, $purchaseOrder->currency);

        if ($rate === null) {
            throw new RuntimeException(
                "No exchange rate found from {$currency->code} to {$purchaseOrder->currency->code} for purchase order {$purchaseOrder->reference}"
            );
        }

        return $netAmount * $rate;
    }
}
