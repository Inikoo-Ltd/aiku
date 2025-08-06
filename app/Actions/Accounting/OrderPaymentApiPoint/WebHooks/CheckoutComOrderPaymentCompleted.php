<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 May 2025 12:38:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\OrderPaymentApiPoint\WebHooks;

use App\Actions\Accounting\OrderPaymentApiPoint\UpdateOrderPaymentApiPoint;
use App\Actions\Accounting\WithCheckoutCom;
use App\Actions\IrisAction;
use App\Actions\Retina\Dropshipping\Orders\WithRetinaOrderPlacedRedirection;
use App\Enums\Accounting\OrderPaymentApiPoint\OrderPaymentApiPointStateEnum;
use App\Models\Accounting\OrderPaymentApiPoint;
use App\Models\Accounting\PaymentAccountShop;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckoutComOrderPaymentCompleted extends IrisAction
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

        if (Arr::get($checkoutComPayment, 'error')) {
            return [
                'status'         => 'pending',
                'payment_status' => 'Connection Error',
                'msg'            => __('Error connecting to payment gateway, we will try again now')

            ];
        }

        $status = Arr::get($checkoutComPayment, 'status', 'Error');
        if (in_array($status, ['Pending', 'Retry Scheduled'])) {
            return [
                'status'         => 'pending',
                'payment_status' => $status,
                'msg'            => __('Payment is still pending, we will try again now')
            ];
        }

        if (in_array($status, ['Voided', 'Declined', 'Cancelled', 'Expired'])) {
            CheckoutComOrderPaymentFailure::make()->processFailure($orderPaymentApiPoint, $checkoutComPayment);

            return [
                'status'         => 'error',
                'payment_status' => $status,
                'msg'            => __('Payment has been declined, please try again later')
            ];
        }

        CheckoutComOrderPaymentSuccess::make()->processSuccessfulPayment($orderPaymentApiPoint, $paymentAccountShop, $checkoutComPayment);


        dd(Arr::get($checkoutComPayment, 'status'));
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
