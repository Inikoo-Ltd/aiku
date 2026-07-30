<?php

/*
 * author Arya Permana - Kirin
 * created on 02-07-2025-15h-20m
 * GitHub: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Accounting\CreditTransaction\StoreCreditTransaction;
use App\Actions\Accounting\Payment\StorePayment;
use App\Actions\Ordering\Order\AttachPaymentToOrder;
use App\Actions\Ordering\Order\UpdateOrder;
use App\Actions\RetinaAction;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Ordering\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SettleRetinaOrderWithBalance extends RetinaAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Order $order): array
    {
        $customer = $order->customer;

        $paymentAccountShop = PaymentAccountShop::where('shop_id', $order->shop_id)->where('type', 'account')->where('state', 'active')->first();

        if (!$paymentAccountShop) {
            return [
                'success' => false,
                'reason'  => 'No payment account found',
                'status'  => PaymentStatusEnum::SUCCESS,
                'state'   => PaymentStateEnum::COMPLETED,
                'type'    => PaymentTypeEnum::PAYMENT

            ];
        }

        /** Round to cents: raw float subtraction of the DB decimals yields values like
         * 0.039999999999999 which StorePayment's decimal:0,2 rule rejects, rolling back
         * the whole payment transaction */
        $amountToPay = round($order->total_amount - $order->payment_amount, 2);

        if ($customer->balance < $amountToPay) {
            $amount = round((float)$customer->balance, 2);
        } else {
            $amount = $amountToPay;
        }

        $paymentData = [
            'reference'               => 'cu-'.$customer->id.'-bal-'.Str::random(10),
            'amount'                  => $amount,
            'status'                  => PaymentStatusEnum::SUCCESS,
            'payment_account_shop_id' => $paymentAccountShop->id
        ];

        $order = DB::transaction(function () use ($order, $customer, $paymentAccountShop, $paymentData, $amount) {
            $payment = StorePayment::make()->action($customer, $paymentAccountShop->paymentAccount, $paymentData);

            AttachPaymentToOrder::make()->action($order, $payment, [
                'amount' => $amount
            ]);

            $order = UpdateOrder::make()->action(order: $order, modelData: [
                'payment_amount' => round($order->payments->sum('amount'), 2)
            ], strict: false);

            $creditTransactionData = [
                'amount'     => -$amount,
                'type'       => CreditTransactionTypeEnum::PAYMENT,
                'payment_id' => $payment->id,
            ];
            StoreCreditTransaction::make()->action($customer, $creditTransactionData);

            return $order;
        });

        return [
            'success' => true,
            'reason'  => 'Order paid successfully',
            'order'   => $order,
        ];
    }
}
