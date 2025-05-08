<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 May 2025 12:38:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\OrderPaymentApiPoint\WebHooks;

use App\Actions\Accounting\WithCheckoutCom;
use App\Actions\OrgAction;
use App\Models\Accounting\OrderPaymentApiPoint;
use App\Models\Accounting\PaymentAccountShop;
use Checkout\CheckoutApiException;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class CheckoutComOrderPaymentSuccess extends OrgAction
{
    use WithCheckoutCom;


    public function handle(OrderPaymentApiPoint $orderPaymentApiPoint, array $modelData): void
    {
        $paymentAccountShopID = Arr::get($orderPaymentApiPoint->data, 'payment_methods.checkout');
        $paymentAccountShop = PaymentAccountShop::find($paymentAccountShopID);
        list($publicKey, $secretKey) = $paymentAccountShop->getCredentials();


        $checkoutApi = $this->getCheckoutApi($publicKey, $secretKey);

        try {
            $response = $checkoutApi->getPaymentsClient()->getPaymentDetails($modelData['cko-payment-id']);
        } catch (CheckoutApiException $e) {
            \Sentry\captureException($e);
            $error_details = $e->error_details;
            $http_status_code = isset($e->http_metadata) ? $e->http_metadata->getStatusCode() : null;
            print $http_status_code.' '.$error_details;
        }

        dd($response);




    }

    public function rules(): array
    {
        return [
            'cko-payment-session-id' => ['sometimes', 'string'],
            'cko-session-id'         => ['sometimes', 'string'],
            'cko-payment-id'         => ['sometimes', 'string'],
        ];
    }

    public function asController(OrderPaymentApiPoint $orderPaymentApiPoint, ActionRequest $request)
    {
        $this->initialisation($orderPaymentApiPoint->organisation, $request);
        $this->handle($orderPaymentApiPoint, $this->validatedData);
    }

}
