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
use App\Actions\Ordering\Order\SubmitOrder;
use App\Actions\RetinaAction;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Ordering\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;

class PayRetinaOrderWithBalance extends RetinaAction
{
    use WithBasketStateWarning;
    use WithRetinaOrderPlacedRedirection;

    /**
     * @throws \Throwable
     */
    public function handle(Order $order): array
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

        if ($order->customer->balance < $order->total_amount) {
            return [
                'success' => false,
                'reason'  => 'Insufficient balance',
                'order'   => $order,
            ];
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
            'status'                  => 'in_process',
            'payment_account_shop_id' => $paymentAccountShop->id
        ];

        $order = DB::transaction(function () use ($order, $customer, $paymentAccountShop, $paymentData) {
            $payment = StorePayment::make()->action($customer, $paymentAccountShop->paymentAccount, $paymentData);

            $creditTransactionData = [
                'amount'     => -$order->total_amount,
                'type'       => CreditTransactionTypeEnum::PAYMENT,
                'payment_id' => $payment->id,
            ];
            StoreCreditTransaction::make()->action($customer, $creditTransactionData);

            return SubmitOrder::run($order);
        });

        return [
            'success' => true,
            'reason'  => 'Order paid successfully',
            'order'   => $order,
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
