<?php

/*
 * Author: Vika Aqordi
 * Created on 26-11-2025-15h-12m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/

namespace App\Actions\CRM\Customer;

use App\Actions\Accounting\CreditTransaction\StoreCreditTransaction;
use App\Actions\Accounting\Payment\StorePayment;
use App\Actions\Ordering\Order\AttachPaymentToOrder;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Ordering\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PayOrderWithCustomerBalance extends OrgAction
{
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    public function handle(Order $order): array
    {

        if ($order->payment_amount == $order->total_amount) {
            return [
                'success' => false,
                'reason'  => 'Order has been paid',
            ];
        }

        if ($order->customer->balance < $order->total_amount) {
            return [
                'success' => false,
                'reason'  => 'Insufficient balance',
            ];
        }

        $paymentAccountShop = PaymentAccountShop::where('shop_id', $order->shop_id)->where('type', 'account')->where('state', 'active')->first();
        if (!$paymentAccountShop) {
            return [
                'success' => false,
                'reason'  => 'PaymentAccountShop not found',
            ];
        }

        $customer = $order->customer;

        $paymentData = [
            'reference'               => 'cu-'.$customer->id.'-bal-'.Str::random(10),
            'amount'                  => $order->total_amount,
            'status'                  => PaymentStatusEnum::SUCCESS,
            'state'                   => PaymentStateEnum::COMPLETED,
            'payment_account_shop_id' => $paymentAccountShop->id
        ];
        DB::transaction(function () use ($order, $customer, $paymentAccountShop, $paymentData) {
            $payment = StorePayment::make()->action($customer, $paymentAccountShop->paymentAccount, $paymentData);

            AttachPaymentToOrder::make()->action($order, $payment, [
                'amount' => $payment->amount
            ]);


            $creditTransactionData = [
                'amount'     => -$payment->amount,
                'type'       => CreditTransactionTypeEnum::PAYMENT,
                'payment_id' => $payment->id,
            ];
            StoreCreditTransaction::make()->action($customer, $creditTransactionData);

            return $order;
        });

        return [
            'success' => true,
        ];

    }

    /**
     * @throws \Throwable
     */
    public function asController(Order $order): void
    {
        $this->initialisationFromShop($order->shop, []);
        $result = $this->handle($order);

        request()->session()->flash('notification', [
            'status'  => $result['success'] ? 'success' : 'error',
            'title'   => $result['type'],
            'description' => ''
        ]);
    }



}
