<?php

/*
 * author Arya Permana - Kirin
 * created on 13-05-2025-15h-06m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\PaymentAccount\Hydrators;

use App\Models\Accounting\PaymentAccount;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class PaymentAccountHydrateCustomers implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(PaymentAccount $paymentAccount): string
    {
        return $paymentAccount->id;
    }


    public function handle(PaymentAccount $paymentAccount): void
    {
        $customers = DB::table('payments')
            ->where('payment_account_id', $paymentAccount->id)
            ->where('type', 'payment')
            ->where('status', 'success')
            ->distinct()
            ->count('customer_id');

        $stats =
        [
            'number_customers' => $customers
        ];

        $paymentAccount->stats()->update($stats);
    }




}
