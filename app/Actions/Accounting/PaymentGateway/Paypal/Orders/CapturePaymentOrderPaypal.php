<?php

namespace App\Actions\Accounting\PaymentGateway\Paypal\Orders;

use App\Actions\Accounting\PaymentGateway\Paypal\Traits\WithPaypalConfiguration;
use App\Actions\Accounting\TopUp\SetTopUpStatusToSuccess;
use App\Models\Accounting\Payment;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class CapturePaymentOrderPaypal
{
    use AsAction;
    use WithPaypalConfiguration;

    public function handle(Payment $payment, ActionRequest $request)
    {
        $provider = $this->getPaypalConfiguration(
            Arr::get($payment->paymentAccount->data, 'paypal_client_id'),
            Arr::get($payment->paymentAccount->data, 'paypal_client_secret')
        );

        $res = $provider->capturePaymentOrder($request->input('token'));

        if (Arr::get($res, 'status') === 'COMPLETED') {
            SetTopUpStatusToSuccess::make()->action($payment->topUp);
        }

        return redirect(route('retina.topup.index'));
    }
}
