<?php
/*
 * author Arya Permana - Kirin
 * created on 13-05-2025-16h-50m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\PaymentAccountShop\Hydrators;

use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Accounting\PaymentServiceProvider;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class PaymentAccountShopHydrateCustomers implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(PaymentAccountShop $paymentAccountShop): string
    {
        return $paymentAccountShop->id;
    }


    public function handle(PaymentAccountShop $paymentAccountShop): void
    {
        $paymentAccount = $paymentAccountShop->paymentAccount;
        $shopId = $paymentAccountShop->shop_id;

        $customers = DB::table('payments')
            ->join('customers', 'payments.customer_id', '=', 'customers.id')
            ->where('payments.payment_account_id', $paymentAccount->id)
            ->where('customers.shop_id', $shopId)
            ->where('payments.type', PaymentTypeEnum::PAYMENT->value)
            ->where('payments.status', PaymentStatusEnum::SUCCESS->value)
            ->distinct()
            ->count('payments.customer_id');

        $stats = [
            'number_customers' => $customers
        ];

        $paymentAccountShop->stats()->update($stats);
    }




}
