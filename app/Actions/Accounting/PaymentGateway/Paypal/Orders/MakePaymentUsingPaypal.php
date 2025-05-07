<?php

namespace App\Actions\Accounting\PaymentGateway\Paypal\Orders;

use App\Actions\Accounting\Payment\UpdatePayment;
use App\Actions\Accounting\PaymentGateway\Paypal\Traits\WithPaypalConfiguration;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Accounting\Payment;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class MakePaymentUsingPaypal
{
    use AsAction;
    use WithActionUpdate;
    use WithPaypalConfiguration;

    public string $commandSignature   = 'paypal:checkout';
    public string $commandDescription = 'Checkout using paypal';

    public function handle(Payment $payment, array $modelData)
    {
        $paypalResponse = StoreOrderToPaypal::run($payment, $modelData);

        data_set($modelData, 'data', $paypalResponse);

        return UpdatePayment::run($payment, [
            'data' => [
                'payment_id' => Arr::get($paypalResponse, 'id'),
                'payment_url' => Arr::get($paypalResponse, 'url')
            ]
        ]);
    }
}
