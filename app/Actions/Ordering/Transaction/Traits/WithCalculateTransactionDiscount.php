<?php

namespace App\Actions\Ordering\Transaction\Traits;

use App\Actions\Ordering\Order\CalculateOrderTotalAmounts;
use App\Actions\Ordering\Order\GenerateInvoiceFromOrder;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Ordering\Transaction;

trait WithCalculateTransactionDiscount
{
    // To fix concurrent issue discount not applied after picking up from Waiting (reported by Erika)
    public function calculateTransactionDiscountTotal(DeliveryNoteItem $deliveryNoteItem)
    {
        $transaction = $deliveryNoteItem->transaction;

        // INI-1811: Guard, is follow on products must always be 0
        if ($transaction->is_follow_on) {
            return;
        }

        // Recalculate the transaction totals (Data below)
        $packedData = GenerateInvoiceFromOrder::make()->recalculateTransactionTotals($transaction, $deliveryNoteItem->deliveryNote);

        $transactionData = [
            'quantity_picked' => $packedData['quantity'],
            'gross_amount'    => $packedData['gross_amount'],
            'net_amount'      => $packedData['net_amount'],
            'org_net_amount'  => $packedData['org_net_amount'],
            'grp_net_amount'  => $packedData['grp_net_amount'],
        ];

        $transaction->update($transactionData);

        // Recalculate Order Total
        CalculateOrderTotalAmounts::run($transaction->order, false, false);
    }
}
