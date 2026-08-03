<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 May 2025 12:38:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\OrderPaymentApiPoint\WebHooks;

use App\Actions\Accounting\WithCheckoutCom;
use App\Actions\IrisAction;
use App\Enums\Accounting\OrderPaymentApiPoint\OrderPaymentApiPointStateEnum;
use App\Models\Accounting\OrderPaymentApiPoint;
use App\Models\Accounting\PaymentAccountShop;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class CheckoutComOrderPaymentCompleted extends IrisAction
{
    use WithCheckoutCom {
        getCheckOutPayment as public;
    }


    /**
     * @throws \Throwable
     */
    public function handle(OrderPaymentApiPoint $orderPaymentApiPoint, array $modelData): array
    {
        if ($orderPaymentApiPoint->state == OrderPaymentApiPointStateEnum::SUCCESS || $orderPaymentApiPoint->order->submitted_at) {
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


        if (Arr::get($checkoutComPayment, 'error')) {
            return [
                'status'         => 'pending',
                'payment_status' => 'Connection Error',
                'msg'            => __('Error connecting to payment gateway, we will try again now')

            ];
        }

        $status = Arr::get($checkoutComPayment, 'status', 'Error');

        /** The checkout page mints a new api point on every view, so after a pending redirect the
         * poll arrives with a fresh api point while the payment belongs to the original one:
         * process against the payment's own api point as long as it is for the same order */
        if (Arr::get($checkoutComPayment, 'metadata.api_point_id') != $orderPaymentApiPoint->id) {
            $paymentOwnApiPoint = OrderPaymentApiPoint::find(Arr::get($checkoutComPayment, 'metadata.api_point_id'));

            if (!$paymentOwnApiPoint || $paymentOwnApiPoint->order_id != $orderPaymentApiPoint->order_id) {
                return [
                    'status'         => 'error',
                    'payment_status' => $status,
                    'msg'            => __('The payment does not belong to this order.')
                ];
            }

            $orderPaymentApiPoint = $paymentOwnApiPoint;
        }

        if (in_array($status, self::CHECKOUT_COM_FAILURE_STATUSES)) {
            CheckoutComOrderPaymentFailure::make()->processFailure($orderPaymentApiPoint, $checkoutComPayment);

            return [
                'status'         => 'error',
                'payment_status' => $status,
                'msg'            => __('Payment has been declined, please try again later')
            ];
        }

        if (in_array($status, self::CHECKOUT_COM_CAPTURED_STATUSES)) {
            return CheckoutComOrderPaymentSuccess::make()->processSuccessfulPayment($orderPaymentApiPoint, $paymentAccountShop, $checkoutComPayment);
        }

        /** Authorized-before-capture and anything unknown stays pending: the client polls and
         * the capture webhook is the authority on money actually moving */
        return [
            'status'         => 'pending',
            'payment_status' => $status,
            'msg'            => __('Payment is still pending, we will try again now')
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
