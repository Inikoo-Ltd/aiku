<?php

namespace App\Actions\Ordering\Transaction\Traits;

use App\Actions\Ordering\Order\CalculateOrderTotalAmounts;
use App\Actions\Ordering\Order\GenerateInvoiceFromOrder;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Ordering\Transaction;
use Illuminate\Support\Facades\DB;

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

        /**
         * Priced off the row's own factor rather than the loaded model's: a discount
         * recalculation running alongside this leaves the model holding the factor of 1 it was
         * wiped to, and the line is written back at full gross while the row still reads as
         * discounted. The order is already paid by then, so the customer is billed the
         * difference - two lines of GB584541 went out 60p over that way.
         */
        DB::table('transactions')->where('id', $transaction->id)
            ->update(
                [
                    'net_amount' => DB::raw('round((gross_amount * coalesce(current_discount_factor, 1))::numeric, 2)'),
                ]
            );

        // Recalculate Order Total
        CalculateOrderTotalAmounts::run($transaction->order, false, false);
    }
}
