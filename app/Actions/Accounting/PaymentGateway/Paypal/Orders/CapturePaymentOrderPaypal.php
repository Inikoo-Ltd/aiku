<?php

namespace App\Actions\Accounting\PaymentGateway\Paypal\Orders;

use App\Actions\Accounting\Payment\UpdatePayment;
use App\Actions\Accounting\PaymentGateway\Paypal\Traits\WithPaypalConfiguration;
use App\Actions\Accounting\TopUp\SetTopUpStatusToSuccess;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Models\Accounting\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class CapturePaymentOrderPaypal
{
    use AsAction;
    use WithPaypalConfiguration;

    /**
     * @throws \Throwable
     */
    public function handle(Payment $payment, ActionRequest $request): RedirectResponse
    {
        $provider = $this->getPaypalProvider($payment->paymentAccount, $payment->currency->code);

        $response = $provider->capturePaymentOrder($request->input('token'));

        if (Arr::get($response, 'status') === 'COMPLETED') {
            UpdatePayment::run($payment, [
                'status' => PaymentStatusEnum::SUCCESS,
                'state'  => PaymentStateEnum::COMPLETED,
                'data'   => [
                    'paypal' => [
                        'capture_id'     => Arr::get($response, 'purchase_units.0.payments.captures.0.id'),
                        'capture_status' => Arr::get($response, 'status'),
                    ]
                ]
            ]);

            if ($payment->topUp) {
                SetTopUpStatusToSuccess::make()->action($payment->topUp);
            }

            return redirect(route('retina.top_up.index'));
        }

        UpdatePayment::run($payment, [
            'status' => PaymentStatusEnum::FAIL,
            'state'  => PaymentStateEnum::ERROR,
            'data'   => [
                'paypal' => [
                    'capture_status' => Arr::get($response, 'status'),
                    'error'          => Arr::get($response, 'error'),
                ]
            ]
        ]);

        return redirect(route('retina.top_up.index'))->with('notification', [
            'status'      => 'error',
            'title'       => __('Error!'),
            'description' => __('Your Paypal payment could not be completed.'),
        ]);
    }
}
