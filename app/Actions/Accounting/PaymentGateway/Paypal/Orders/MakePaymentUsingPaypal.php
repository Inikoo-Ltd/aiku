<?php

namespace App\Actions\Accounting\PaymentGateway\Paypal\Orders;

use App\Actions\Accounting\Payment\UpdatePayment;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Accounting\Payment;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class MakePaymentUsingPaypal
{
    use AsAction;
    use WithActionUpdate;

    public function handle(Payment $payment, array $modelData): Payment
    {
        $paypalResponse = StoreOrderToPaypal::run($payment, $modelData);

        return UpdatePayment::run($payment, [
            'data' => [
                'paypal' => [
                    'order_id'    => Arr::get($paypalResponse, 'id'),
                    'payment_url' => Arr::get($paypalResponse, 'url'),
                    'status'      => Arr::get($paypalResponse, 'status'),
                ]
            ]
        ]);
    }
}
