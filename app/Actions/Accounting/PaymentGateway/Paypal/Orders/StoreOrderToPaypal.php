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

    /**
     * @return array{id: string, url: string, status: string}
     * @throws \Throwable
     */
    public function handle(Payment $payment, array $modelData): array
    {
        $provider = $this->getPaypalProvider($payment->paymentAccount, $payment->currency->code);

        $orderData = [
            'intent'         => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => (string)$payment->id,
                    'amount'       => [
                        'value'         => number_format((float)$payment->amount, 2, '.', ''),
                        'currency_code' => $payment->currency->code
                    ]
                ]
            ],
            'application_context' => [
                'return_url' => Arr::get($modelData, 'return_url', route('retina.top_up.paypal.capture_payment', $payment->id)),
                'cancel_url' => Arr::get($modelData, 'cancel_url', route('retina.top_up.paypal.cancel_payment', $payment->id)),
            ]
        ];

        $response = $provider->createOrder($orderData);

        if (!Arr::get($response, 'id')) {
            throw new \RuntimeException('[Paypal] Could not create order: '.json_encode(Arr::get($response, 'error', $response)));
        }

        $approveUrl = Arr::get(
            collect(Arr::get($response, 'links', []))->firstWhere('rel', 'approve'),
            'href',
            Arr::get($response, 'links.1.href')
        );

        return [
            'id'     => Arr::get($response, 'id'),
            'url'    => $approveUrl,
            'status' => Arr::get($response, 'status')
        ];
    }
}
