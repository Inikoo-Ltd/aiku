<?php

namespace App\Actions\Accounting\PaymentGateway\Paypal\Orders;

use App\Actions\Accounting\Payment\UpdatePayment;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Models\Accounting\Payment;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\Concerns\AsAction;

class CancelPaymentOrderPaypal
{
    use AsAction;

    public function handle(Payment $payment): RedirectResponse
    {
        if ($payment->state == PaymentStateEnum::IN_PROCESS) {
            UpdatePayment::run($payment, [
                'status'       => PaymentStatusEnum::FAIL,
                'state'        => PaymentStateEnum::CANCELLED,
                'cancelled_at' => now(),
            ]);
        }

        return redirect(route('retina.top_up.index'))->with('notification', [
            'status'      => 'error',
            'title'       => __('Cancelled'),
            'description' => __('Your Paypal payment was cancelled.'),
        ]);
    }
}
