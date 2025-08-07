<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 May 2025 12:38:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\OrderPaymentApiPoint\WebHooks;

use App\Actions\Accounting\OrderPaymentApiPoint\UpdateOrderPaymentApiPoint;
use App\Actions\Accounting\Payment\StorePayment;
use App\Actions\Accounting\WithCheckoutCom;
use App\Actions\IrisAction;
use App\Actions\Ordering\Order\AttachPaymentToOrder;
use App\Actions\Ordering\Order\SubmitOrder;
use App\Actions\Retina\Dropshipping\Orders\SettleRetinaOrderWithBalance;
use App\Actions\Retina\Dropshipping\Orders\WithRetinaOrderPlacedRedirection;
use App\Enums\Accounting\OrderPaymentApiPoint\OrderPaymentApiPointStateEnum;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Models\Accounting\OrderPaymentApiPoint;
use App\Models\Accounting\PaymentAccountShop;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckoutComOrderPaymentSuccess extends IrisAction
{
    use AsAction;
    use WithCheckoutCom;
    use WithRetinaOrderPlacedRedirection;


    /**
     * @throws \Throwable
     */
    public function handle(OrderPaymentApiPoint $orderPaymentApiPoint, array $modelData): array
    {
        $paymentAccountShopID = Arr::get($orderPaymentApiPoint->data, 'payment_methods.checkout');
        $paymentAccountShop   = PaymentAccountShop::find($paymentAccountShopID);

        $checkoutComPayment = $this->getCheckOutPayment(
            $paymentAccountShop,
            $modelData['cko-payment-id']
        );


        return $this->processSuccessfulPayment($orderPaymentApiPoint, $paymentAccountShop, $checkoutComPayment);
    }

    /**
     * @throws \Throwable
     */
    public function processSuccessfulPayment(OrderPaymentApiPoint $orderPaymentApiPoint, PaymentAccountShop $paymentAccountShop, array $checkoutComPayment): array
    {
        $amount = Arr::get($checkoutComPayment, 'amount', 0) / 100;

        $paymentData = [
            'reference'               => Arr::get($checkoutComPayment, 'id'),
            'amount'                  => $amount,
            'status'                  => PaymentStatusEnum::SUCCESS,
            'state'                   => PaymentStateEnum::COMPLETED,
            'type'                    => PaymentTypeEnum::PAYMENT,
            'payment_account_shop_id' => $paymentAccountShop->id,
            'api_point_type'          => class_basename($orderPaymentApiPoint),
            'api_point_id'            => $orderPaymentApiPoint->id,
        ];


        $order = DB::transaction(function () use ($orderPaymentApiPoint, $paymentAccountShop, $paymentData) {
            $payment = StorePayment::make()->action($orderPaymentApiPoint->order->customer, $paymentAccountShop->paymentAccount, $paymentData);

            $order = $orderPaymentApiPoint->order;

            AttachPaymentToOrder::make()->action($order, $payment, [
                'amount' => $payment->amount
            ]);


            if ($order->total_amount > $order->payment_amount && $order->customer->balance > 0) {
                SettleRetinaOrderWithBalance::run($order);
            }

            UpdateOrderPaymentApiPoint::run(
                $orderPaymentApiPoint,
                [
                    'state'        => OrderPaymentApiPointStateEnum::SUCCESS,
                    'processed_at' => now(),
                    'data'         => [
                        'payment_id' => $payment->id,
                    ]
                ]
            );


            $order->refresh();

            return SubmitOrder::run($order);
        });

        return [
            'status'   => 'success',
            'success'  => true,
            'reason'   => 'Order paid successfully',
            'order'    => $order,
            'order_id' => $order->id,

        ];
    }


    public function rules(): array
    {
        return [
            'cko-payment-id' => ['required', 'string'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(OrderPaymentApiPoint $orderPaymentApiPoint, ActionRequest $request): array
    {
        $this->initialisation($request);

        return $this->handle($orderPaymentApiPoint, $this->validatedData);
    }

}
