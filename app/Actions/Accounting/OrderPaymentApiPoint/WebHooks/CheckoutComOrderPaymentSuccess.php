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
use App\Actions\Ordering\Order\UpdateState\SubmitOrder;
use App\Actions\Retina\Dropshipping\Orders\SettleRetinaOrderWithBalance;
use App\Actions\Retina\Dropshipping\Orders\WithRetinaOrderPlacedRedirection;
use App\Enums\Accounting\OrderPaymentApiPoint\OrderPaymentApiPointStateEnum;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Accounting\OrderPaymentApiPoint;
use App\Models\Accounting\Payment;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;

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
        if ($orderPaymentApiPoint->state == OrderPaymentApiPointStateEnum::SUCCESS) {
            return [
                'status'   => 'success',
                'success'  => true,
                'reason'   => 'Order paid successfully',
                'order'    => $orderPaymentApiPoint->order,
                'order_id' => $orderPaymentApiPoint->order->id,

            ];
        }

        $paymentAccountShopID = Arr::get($orderPaymentApiPoint->data, 'payment_methods.checkout');
        $paymentAccountShop   = PaymentAccountShop::find($paymentAccountShopID);

        $checkoutComPayment = $this->getCheckOutPayment(
            $paymentAccountShop,
            $modelData['cko-payment-id']
        );

        $status = Arr::get($checkoutComPayment, 'status');

        $paymentApiPointId = Arr::get($checkoutComPayment, 'metadata.api_point_id');
        if (!Arr::get($checkoutComPayment, 'error') && $paymentApiPointId != $orderPaymentApiPoint->id) {
            return [
                'status'   => 'failure',
                'success'  => false,
                'reason'   => __('The payment does not belong to this order.'),
                'order'    => $orderPaymentApiPoint->order,
                'order_id' => $orderPaymentApiPoint->order->id,
            ];
        }

        if (in_array($status, self::CHECKOUT_COM_FAILURE_STATUSES)) {
            CheckoutComOrderPaymentFailure::make()->processFailure($orderPaymentApiPoint, $checkoutComPayment);

            return [
                'status'   => 'failure',
                'success'  => false,
                'reason'   => $this->getFailureMessage($status),
                'order'    => $orderPaymentApiPoint->order,
                'order_id' => $orderPaymentApiPoint->order->id,
            ];
        }

        if (in_array($status, self::CHECKOUT_COM_CAPTURED_STATUSES)) {
            return $this->processSuccessfulPayment($orderPaymentApiPoint, $paymentAccountShop, $checkoutComPayment);
        }

        /** Anything else (API error, Pending, Authorized-before-capture, unknown) waits for the
         * capture webhook: money must be captured before the order is marked as paid */
        return [
            'status'         => 'pending',
            'success'        => false,
            'reason'         => __('Your payment is still being confirmed. Your order will be submitted automatically once the payment is confirmed.'),
            'order'          => $orderPaymentApiPoint->order,
            'order_id'       => $orderPaymentApiPoint->order->id,
            'cko_payment_id' => $modelData['cko-payment-id'],
        ];
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
            'source'                  => Arr::get($checkoutComPayment, 'source'),
        ];


        $order = DB::transaction(function () use ($orderPaymentApiPoint, $paymentAccountShop, $paymentData) {
            /** @var OrderPaymentApiPoint $orderPaymentApiPoint locked to stop the client callback, the redirect and the webhook processing the same payment twice */
            $orderPaymentApiPoint = OrderPaymentApiPoint::lockForUpdate()->find($orderPaymentApiPoint->id);

            $paymentAlreadyStored = Payment::where('payment_account_shop_id', $paymentAccountShop->id)
                ->where('reference', $paymentData['reference'])
                ->exists();

            if ($paymentAlreadyStored) {
                return $orderPaymentApiPoint->order;
            }

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

            return $order;
        });

        /** Submit runs AFTER the payment transaction commits: a submit failure must never roll
         * back the payment record — that 422s the webhook, checkout.com retries for hours and
         * the customer retries paying. Failed submit = paid order in basket + Sentry alert.
         * The order row lock serialises racing submitters (client callback vs webhook): the
         * loser re-reads the state as submitted and skips instead of failing on duplicates. */
        if ($order->state == OrderStateEnum::CREATING) {
            try {
                $order = DB::transaction(function () use ($order) {
                    $lockedOrder = Order::lockForUpdate()->find($order->id);

                    if ($lockedOrder->state == OrderStateEnum::CREATING) {
                        return SubmitOrder::run($lockedOrder);
                    }

                    return $lockedOrder;
                });
            } catch (\Throwable $e) {
                Sentry::captureException($e);
                Sentry::captureMessage(
                    'Checkout.com payment recorded but order '.$order->id.' failed to submit — paid order left in basket, needs attention'
                );
            }
        } elseif ($order->state == OrderStateEnum::CANCELLED) {
            Sentry::captureMessage(
                'Captured checkout.com payment recorded on cancelled order '.$order->id.' — refund may be needed'
            );
        }

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
