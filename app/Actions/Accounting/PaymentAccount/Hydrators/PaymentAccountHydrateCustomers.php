<?php
/*
 * author Arya Permana - Kirin
 * created on 13-05-2025-15h-06m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\PaymentAccount\Hydrators;

use App\Actions\Traits\Hydrators\WithPaymentAggregators;
use App\Actions\Traits\WithEnumStats;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Models\Accounting\Payment;
use App\Models\Accounting\PaymentAccount;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class PaymentAccountHydrateCustomers implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;
    use WithPaymentAggregators;

    public function getJobUniqueId(PaymentAccount $paymentAccount): string
    {
        return $paymentAccount->id;
    }


    public function handle(PaymentAccount $paymentAccount): void
    {
        $customers = $paymentAccount->payments()
            ->with('customer')
            ->get()
            ->pluck('customer')
            ->unique('id')
            ->count();

        $stats = 
        [
            'number_customers' => $customers
        ];

        $paymentAccount->stats()->update($stats);
    }




}
