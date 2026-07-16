<?php

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Accounting\Payment\UpdatePayment;
use App\Actions\RetinaWebhookAction;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Accounting\Payment;
use App\Models\Ordering\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class CancelOrderWithPaypal extends RetinaWebhookAction
{
    public function handle(Order $order, Payment $payment): Payment
    {
        if ($payment->state == PaymentStateEnum::IN_PROCESS) {
            $payment = UpdatePayment::run($payment, [
                'status'       => PaymentStatusEnum::FAIL,
                'state'        => PaymentStateEnum::CANCELLED,
                'cancelled_at' => now(),
            ]);
        }

        return $payment;
    }

    public function asController(Order $order, Payment $payment, ActionRequest $request): RedirectResponse
    {
        $this->initialisation($request);

        abort_unless(
            $payment->customer_id == $order->customer_id
            && Arr::get($payment->data, 'order_id') == $order->id,
            404
        );

        $this->handle($order, $payment);

        $notification = [
            'status'      => 'error',
            'title'       => __('Cancelled'),
            'description' => __('Your Paypal payment was cancelled.'),
        ];

        if ($order->shop->type == ShopTypeEnum::DROPSHIPPING) {
            return Redirect::route('retina.dropshipping.checkout.show', [$order->slug])->with('modal', $notification);
        }

        return Redirect::route('retina.ecom.checkout.show')->with('modal', $notification);
    }
}
