<?php

namespace App\Actions\Ordering\Transaction\Traits;

use App\Models\Ordering\Transaction;
use Illuminate\Support\Facades\DB;

trait WithCalculateTransactionDiscount
{
    // To fix concurrent issue discount not applied after picking up from Waiting (reported by Erika)
    public function calculateTransactionDiscountTotal(Transaction $transaction)
    {
        if ($transaction->current_discount_factor) {
            $discountedAmount = round((float) $transaction->gross_amount * $transaction->current_discount_factor, 2);

            DB::table('transactions')->where('id', $transaction->id)
                ->update(
                    [
                        'net_amount'              => $discountedAmount,
                    ]
                );
        }
    }
}
