<?php

namespace App\Actions\Ordering\Transaction\Traits;

use App\Actions\Ordering\Order\CalculateOrderDiscounts;
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

        // Check if transaction have valid offer after picked is changed
        CalculateOrderDiscounts::run($transaction->order);

        // Reupdate based on Curent Discount Factor
        if ($transaction->current_discount_factor) {
            $discountedAmount = round((float) $transaction->gross_amount * $transaction->current_discount_factor, 2);

            DB::table('transactions')->where('id', $transaction->id)
                ->update(
                    [
                        'net_amount'              => $discountedAmount,
                    ]
                );
        }

        // Recalculate Order Total
        CalculateOrderTotalAmounts::run($transaction->order, false, false);
    }
}
