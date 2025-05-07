<?php

namespace App\Actions\Accounting\PaymentGateway\Paypal\Orders;

use App\Actions\Accounting\PaymentGateway\Paypal\Traits\WithPaypalConfiguration;
use App\Models\Accounting\Payment;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreOrderToPaypal
{
    use AsAction;
    use WithPaypalConfiguration;

    public string $commandSignature   = 'paypal:checkout';
    public string $commandDescription = 'Checkout using paypal';

    /**
     * @throws \Throwable
     */
    public function handle(Payment $payment, array $orderData)
    {
        $provider = $this->getPaypalConfiguration(
            Arr::get($payment->paymentAccount->data, 'paypal_client_id'),
            Arr::get($payment->paymentAccount->data, 'paypal_client_secret')
        );

        data_set($orderData, 'purchase_units', [
            [
                'amount' => [
                    'value'         => $payment->amount,
                    'currency_code' => $payment->currency->code
                ]
            ]
        ]);

        data_set($orderData, 'intent', 'CAPTURE');
        data_set($orderData, 'application_context', [
            "return_url" => route('retina.topup.paypal.capture_payment', $payment->id),
            "cancel_url" => route('retina.topup.paypal.cancel_payment', $payment->id)
        ]);

        $response = $provider->createOrder($orderData);

        return [
            'id' => Arr::get($response, 'id'),
            'url' => Arr::get($response, 'links.1.href')
        ];
    }
}
