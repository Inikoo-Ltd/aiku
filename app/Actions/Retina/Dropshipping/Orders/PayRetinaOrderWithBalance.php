<?php

/*
 * author Arya Permana - Kirin
 * created on 16-05-2025-16h-10m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Accounting\CreditTransaction\StoreCreditTransaction;
use App\Actions\Accounting\Payment\StorePayment;
use App\Actions\Ordering\Order\AttachPaymentToOrder;
use App\Actions\Ordering\Order\UpdateState\SubmitOrder;
use App\Actions\RetinaAction;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;
use Sentry;

class PayRetinaOrderWithBalance extends RetinaAction
{
    use WithBasketStateWarning;
    use WithRetinaOrderPlacedRedirection;

    /**
     * @throws \Throwable
     */
    public function handle(Order $order, bool $submitOrder = true): array
    {
        $warning = $this->getWarnings($order);

        if ($warning) {
            return $warning;
        }

        if ($order->payment_amount == $order->total_amount) {
            return [
                'success' => false,
                'reason'  => 'Order has been paid',
                'order'   => $order,
            ];
        }

        $insufficientBalance = [
            'success' => false,
            'reason'  => 'Insufficient balance',
            'order'   => $order,
        ];

        if ($order->customer->spendableBalance() < $order->total_amount) {
            return $insufficientBalance;
        }

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
        $paymentData = [
            'reference'               => 'cu-'.$customer->id.'-bal-'.Str::random(10),
            'amount'                  => $order->total_amount,
            'status'                  => PaymentStatusEnum::SUCCESS,
            'state'                   => PaymentStateEnum::COMPLETED,
            'payment_account_shop_id' => $paymentAccountShop->id
        ];

        $paidOrder = DB::transaction(function () use ($order, $customer, $paymentAccountShop, $paymentData, $submitOrder) {
            $customer = Customer::lockForUpdate()->findOrFail($customer->id);
            if ($customer->spendableBalance() < $order->total_amount) {
                return null;
            }

            $payment = StorePayment::make()->action($customer, $paymentAccountShop->paymentAccount, $paymentData);

            AttachPaymentToOrder::make()->action($order, $payment, [
                'amount' => $payment->amount
            ]);


            $creditTransactionData = [
                'amount'     => -$payment->amount,
                'type'       => CreditTransactionTypeEnum::PAYMENT,
                'payment_id' => $payment->id,
            ];
            $creditTransaction = StoreCreditTransaction::make()->action($customer, $creditTransactionData);

            $paymentAmount = round(-$payment->amount, 2);
            $creditTransactionAmount = round($creditTransaction->amount, 2);
            $diff = $paymentAmount - $creditTransactionAmount;

            if ($diff != 0) {
                Sentry::captureMessage('Payment amount and credit transaction amount do not match Order:'.$order->id.
                ' Payment amount:'.$paymentAmount.' Credit transaction amount:'.$creditTransactionAmount);
            }


            if ($submitOrder) {
                return SubmitOrder::run($order);
            }

            return $order;
        });

        if (!$paidOrder) {
            return $insufficientBalance;
        }

        return [
            'success' => true,
            'reason'  => 'Order paid successfully',
            'order'   => $paidOrder,
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        $order = $request->route('order');
        if ($order->customer_id == $request->user()->customer_id) {
            return true;
        }

        return false;
    }

    /**
     * @throws \Throwable
     */
    public function asController(Order $order, ActionRequest $request): array
    {
        $this->initialisation($request);

        return $this->handle($order);
    }


}
