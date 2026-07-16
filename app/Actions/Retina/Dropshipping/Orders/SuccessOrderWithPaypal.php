<?php

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Accounting\Payment\UpdatePayment;
use App\Actions\Accounting\PaymentGateway\Paypal\Traits\WithPaypalConfiguration;
use App\Actions\Ordering\Order\AttachPaymentToOrder;
use App\Actions\Ordering\Order\UpdateState\SubmitOrder;
use App\Actions\RetinaWebhookAction;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Accounting\Payment;
use App\Models\Ordering\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class SuccessOrderWithPaypal extends RetinaWebhookAction
{
    use WithPaypalConfiguration;
    use WithRetinaOrderPlacedRedirection;

    /**
     * @throws \Throwable
     */
    public function handle(Order $order, Payment $payment, array $modelData): array
    {
        if ($payment->status == PaymentStatusEnum::SUCCESS) {
            return [
                'status'   => 'success',
                'success'  => true,
                'reason'   => 'Order paid successfully',
                'order'    => $order,
                'order_id' => $order->id,
            ];
        }

        $provider = $this->getPaypalProvider($payment->paymentAccount, $payment->currency->code);

        $response = $provider->capturePaymentOrder(Arr::get($modelData, 'token'));

        if (Arr::get($response, 'status') !== 'COMPLETED') {
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

            return [
                'status'  => 'error',
                'success' => false,
                'reason'  => 'Paypal payment was not completed',
                'order'   => $order,
            ];
        }

        $order = DB::transaction(function () use ($order, $payment, $response) {
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

            AttachPaymentToOrder::make()->action($order, $payment, [
                'amount' => $payment->amount,
            ]);

            if ($order->total_amount > $order->payment_amount && $order->customer->balance > 0) {
                SettleRetinaOrderWithBalance::run($order);
            }

            $order->refresh();

            return SubmitOrder::run($order);
        });

        return [
            'status'   => 'success',
            'success'  => true,
            'reason'   => 'Order paid successfully',
            'order'    => $order,
            'order_id' => $order->id,
        ];
    }

    public function rules(): array
    {
        return [
            'token'   => ['required', 'string'],
            'PayerID' => ['sometimes', 'string'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(Order $order, Payment $payment, ActionRequest $request): array|RedirectResponse
    {
        $this->initialisation($request);

        abort_unless(
            $payment->customer_id == $order->customer_id
            && Arr::get($payment->data, 'order_id') == $order->id,
            404
        );

        $result = $this->handle($order, $payment, $this->validatedData);

        if (!Arr::get($result, 'success')) {
            $notification = [
                'status'      => 'error',
                'title'       => __('Error!'),
                'description' => __('Your Paypal payment could not be completed.'),
            ];

            if ($order->shop->type == ShopTypeEnum::DROPSHIPPING) {
                return Redirect::route('retina.dropshipping.checkout.show', [$order->slug])->with('modal', $notification);
            }

            return Redirect::route('retina.ecom.checkout.show')->with('modal', $notification);
        }

        return $result;
    }
}
