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
use App\Actions\Ordering\Order\UpdateState\SendOrderToWarehouse;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Ordering\Order\OrderPayStatusEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Sentry;

class PayOrderWithCustomerBalance extends OrgAction
{
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    public function handle(Order $order, bool $canUseCredit = true): array
    {
        /** Round to cents: raw float subtraction of the DB decimals yields values like
         * 0.039999999999999 which StorePayment's decimal:0,2 rule rejects */
        $toPayAmount = round($order->total_amount - $order->payment_amount, 2);

        if ($toPayAmount <= 0) {
            return [
                'success' => false,
                'reason'  => 'Order has been paid',
            ];
        }

        if ($this->spendable($order->customer, $canUseCredit) <= 0) {
            return [
                'success' => false,
                'reason'  => 'Customer has no balance',
            ];
        }

        $paymentAccountShop = PaymentAccountShop::where('shop_id', $order->shop_id)->where('type', 'account')->where('state', 'active')->first();
        if (!$paymentAccountShop) {
            return [
                'success' => false,
                'reason'  => 'PaymentAccountShop not found',
            ];
        }

        $paid = DB::transaction(function () use ($order, $toPayAmount, $paymentAccountShop, $canUseCredit) {
            $customer    = Customer::lockForUpdate()->findOrFail($order->customer_id);
            $toPayAmount = round(min($toPayAmount, $this->spendable($customer, $canUseCredit)), 2);
            if ($toPayAmount <= 0) {
                return false;
            }

            $paymentData = [
                'reference'               => 'cu-'.$customer->id.'-bal-'.Str::random(10),
                'amount'                  => $toPayAmount,
                'status'                  => PaymentStatusEnum::SUCCESS,
                'state'                   => PaymentStateEnum::COMPLETED,
                'payment_account_shop_id' => $paymentAccountShop->id
            ];

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

            return true;
        });

        if (!$paid) {
            return [
                'success' => false,
                'reason'  => 'Customer has no balance',
            ];
        }

        /** Outside the payment transaction on purpose: routing to the warehouse creates a delivery
         * note, and for a services only order finalises and dispatches it. A failure in any of that
         * must not roll back money the customer has already been charged. */
        if ($order->refresh()->state == OrderStateEnum::SUBMITTED && $order->pay_status == OrderPayStatusEnum::PAID) {
            try {
                SendOrderToWarehouse::make()->action($order, []);
            } catch (\Throwable $e) {
                Sentry::captureException($e);
            }
        }

        return [
            'success' => true,
            'reason'  => 'Order paid successfully',
        ];
    }

    /**
     * @throws \Throwable
     */
    private function spendable(Customer $customer, bool $canUseCredit): float
    {
        return $canUseCredit ? $customer->spendableBalance() : (float)$customer->balance;
    }

    public function asController(Order $order): void
    {
        $this->initialisationFromShop($order->shop, []);
        $canUseCredit = request()->user()->authTo([
            "crm.{$this->shop->id}.edit",
            "accounting.{$this->organisation->id}.edit",
        ]);
        $result = $this->handle($order, $canUseCredit);

        request()->session()->flash('notification', [
            'status'      => $result['success'] ? 'success' : 'error',
            'title'       => Arr::get($result, 'reason', ''),
            'description' => ''
        ]);
    }


}
