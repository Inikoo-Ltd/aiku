<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\PreOrder;

use App\Actions\Accounting\CreditTransaction\StoreCreditTransaction;
use App\Actions\Accounting\Payment\StorePayment;
use App\Actions\Ordering\Order\AttachPaymentToOrder;
use App\Actions\Ordering\Order\UpdateOrderPaymentsStatus;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Ordering\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The checkout took one payment for both parts of a split basket (HELP-3432). A payment belongs
 * to one order, so what the in-stock order no longer needs goes through the customer's balance
 * to the pre-order: money back on one, a balance payment on the other, net zero on the balance.
 */
class MoveOrderExcessPaymentToPreOrder
{
    use AsObject;

    /**
     * @throws \Throwable
     */
    public function handle(Order $order, Order $preOrderOrder): float
    {
        $order         = UpdateOrderPaymentsStatus::run($order);
        $preOrderOrder = UpdateOrderPaymentsStatus::run($preOrderOrder);

        $amount = round(min(
            (float) $order->payment_amount - (float) $order->total_amount,
            (float) $preOrderOrder->total_amount - (float) $preOrderOrder->payment_amount
        ), 2);

        if ($amount <= 0) {
            return 0;
        }

        $paymentAccountShop = PaymentAccountShop::where('shop_id', $order->shop_id)
            ->where('type', PaymentAccountTypeEnum::ACCOUNT)
            ->where('state', 'active')
            ->firstOrFail();

        DB::transaction(function () use ($order, $preOrderOrder, $amount, $paymentAccountShop) {
            $customer = $order->customer;

            $moneyBack = StorePayment::make()->action($customer, $paymentAccountShop->paymentAccount, [
                'reference'               => 'cu-'.$customer->id.'-pre-order-'.Str::random(10),
                'amount'                  => -$amount,
                'status'                  => PaymentStatusEnum::SUCCESS,
                'state'                   => PaymentStateEnum::COMPLETED,
                'type'                    => PaymentTypeEnum::REFUND,
                'payment_account_shop_id' => $paymentAccountShop->id,
            ]);
            AttachPaymentToOrder::make()->action($order, $moneyBack, ['amount' => $moneyBack->amount]);
            StoreCreditTransaction::make()->action($customer, [
                'amount'     => $amount,
                'type'       => CreditTransactionTypeEnum::FROM_EXCESS,
                'payment_id' => $moneyBack->id,
                'notes'      => __('Paid at checkout for pre-order :reference', ['reference' => $preOrderOrder->reference]),
            ], notifyCustomer: false);

            $payment = StorePayment::make()->action($customer, $paymentAccountShop->paymentAccount, [
                'reference'               => 'cu-'.$customer->id.'-bal-'.Str::random(10),
                'amount'                  => $amount,
                'status'                  => PaymentStatusEnum::SUCCESS,
                'state'                   => PaymentStateEnum::COMPLETED,
                'payment_account_shop_id' => $paymentAccountShop->id,
            ]);
            AttachPaymentToOrder::make()->action($preOrderOrder, $payment, ['amount' => $payment->amount]);
            StoreCreditTransaction::make()->action($customer, [
                'amount'     => -$amount,
                'type'       => CreditTransactionTypeEnum::PAYMENT,
                'payment_id' => $payment->id,
            ], notifyCustomer: false);
        });

        UpdateOrderPaymentsStatus::run($order);
        UpdateOrderPaymentsStatus::run($preOrderOrder);

        return $amount;
    }
}
