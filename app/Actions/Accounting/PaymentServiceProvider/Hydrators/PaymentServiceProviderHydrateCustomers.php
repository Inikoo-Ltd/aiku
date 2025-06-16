<?php

/*
 * author Arya Permana - Kirin
 * created on 13-05-2025-16h-46m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\PaymentServiceProvider\Hydrators;

use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Models\Accounting\PaymentServiceProvider;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class PaymentServiceProviderHydrateCustomers implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(PaymentServiceProvider $paymentServiceProvider): string
    {
        return $paymentServiceProvider->id;
    }


    public function handle(PaymentServiceProvider $paymentServiceProvider): void
    {
        $paymentAccountIds = $paymentServiceProvider->accounts()->pluck('id');

        $customers = DB::table('payments')
            ->whereIn('payment_account_id', $paymentAccountIds)
            ->where('type', PaymentTypeEnum::PAYMENT->value)
            ->where('status', PaymentStatusEnum::SUCCESS->value)
            ->distinct()
            ->count('customer_id');

        $stats = [
            'number_customers' => $customers
        ];

        $paymentServiceProvider->stats()->update($stats);
    }




}
