<?php

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Accounting\Payment\StorePayment;
use App\Actions\Accounting\PaymentGateway\Paypal\Orders\MakePaymentUsingPaypal;
use App\Actions\Accounting\Traits\CalculatesPaymentWithBalance;
use App\Actions\RetinaAction;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Enums\Accounting\PaymentAccountShop\PaymentAccountShopStateEnum;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class PayOrderWithPaypal extends RetinaAction
{
    use CalculatesPaymentWithBalance;

    public function handle(Order $order, array $modelData): array
    {
        /** @var PaymentAccountShop $paymentAccountShop */
        $paymentAccountShop = $order->shop->paymentAccountShops()
            ->where('type', PaymentAccountTypeEnum::PAYPAL)
            ->where('state', PaymentAccountShopStateEnum::ACTIVE)
            ->first();

        if (!$paymentAccountShop) {
            return [
                'status'  => 'error',
                'message' => __('Paypal is not enabled in this shop'),
            ];
        }

        $paymentAmounts = $this->calculatePaymentWithBalance(
            $order->total_amount,
            $order->customer->balance
        );

        $toPay = round($paymentAmounts['by_other'], 2);

        if ($toPay == 0) {
            return [
                'status' => 'ok',
            ];
        }

        try {
            $payment = StorePayment::make()->action(
                $order->customer,
                $paymentAccountShop->paymentAccount,
                [
                    'amount'                  => $toPay,
                    'type'                    => PaymentTypeEnum::PAYMENT,
                    'payment_account_shop_id' => $paymentAccountShop->id,
                    'data'                    => [
                        'order_id' => $order->id
                    ],
                ]
            );

            $payment = MakePaymentUsingPaypal::run($payment, [
                'return_url' => route('retina.webhooks.paypal.order_payment_success', [$order->id, $payment->id]),
                'cancel_url' => route('retina.webhooks.paypal.order_payment_cancel', [$order->id, $payment->id]),
            ]);

            return [
                'status' => 'ok',
                'data'   => Arr::get($payment->data, 'paypal.payment_url')
            ];
        } catch (\Exception $e) {
            return [
                'debug'   => 'PayOrderWithPaypal.php',
                'status'  => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    public function authorize(ActionRequest $request): bool
    {
        $order = $request->route('order');

        return $order->customer_id == $this->customer->id;
    }

    public function asController(Order $order, ActionRequest $request): array
    {
        $this->initialisation($request);

        return $this->handle($order, $this->validatedData);
    }
}
