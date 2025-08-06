<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 May 2025 12:38:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\OrderPaymentApiPoint\WebHooks;

use App\Actions\Accounting\WithCheckoutCom;
use App\Actions\IrisAction;
use App\Actions\Retina\Dropshipping\Orders\WithRetinaOrderPlacedRedirection;
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


        dd($checkoutComPayment);
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
